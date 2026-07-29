<?php

namespace Tests\Feature\People;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

final class ApplicationContextEvidencePersistenceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidEvidenceRows')]
    public function test_postgres_rejects_invalid_version_and_interval(
        array $override,
        string $constraint,
    ): void {
        $this->requirePostgres();
        $personId = $this->createPerson();

        try {
            DB::table('person_application_context_evidence')->insert(array_merge(
                $this->evidenceRow($personId),
                $override,
            ));
            self::fail("PostgreSQL must reject {$constraint}.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($constraint, $exception->getMessage());
        }
    }

    #[DataProvider('invalidSuccessorRows')]
    public function test_postgres_serializes_monotonic_nonoverlapping_versions(
        array $override,
        string $message,
    ): void {
        $this->requirePostgres();
        $personId = $this->createPerson();
        DB::table('person_application_context_evidence')->insert(array_merge(
            $this->evidenceRow($personId),
            ['stale_at' => CarbonImmutable::parse('2026-06-01T00:00:00Z')],
        ));
        $successor = array_merge($this->evidenceRow($personId), [
            'id' => '57000000-0000-4000-8000-000000000002',
            'version' => 2,
            'effective_at' => CarbonImmutable::parse('2026-06-01T00:00:00Z'),
            'stale_at' => CarbonImmutable::parse('2027-01-01T00:00:00Z'),
        ], $override);

        try {
            DB::table('person_application_context_evidence')->insert($successor);
            self::fail("PostgreSQL must reject {$message}.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    #[DataProvider('forbiddenMutations')]
    public function test_postgres_rejects_update_delete_and_reparent(
        string $mutation,
    ): void {
        $this->requirePostgres();
        $personId = $this->createPerson();
        $otherPersonId = $this->createPerson();
        DB::table('person_application_context_evidence')->insert(
            $this->evidenceRow($personId),
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('person application context evidence is append-only');
        match ($mutation) {
            'update' => DB::table('person_application_context_evidence')
                ->where('id', '57000000-0000-4000-8000-000000000001')
                ->update(['facts_encrypted' => 'changed']),
            'delete' => DB::table('person_application_context_evidence')
                ->where('id', '57000000-0000-4000-8000-000000000001')
                ->delete(),
            'reparent' => DB::table('person_application_context_evidence')
                ->where('id', '57000000-0000-4000-8000-000000000001')
                ->update(['person_id' => $otherPersonId]),
        };
    }

    #[Group('service-integration')]
    public function test_real_postgres_concurrent_same_version_inserts_only_one_row(): void
    {
        $this->requirePostgres();
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            self::markTestSkipped('pcntl/posix is required for PostgreSQL race proof.');
        }

        $personId = $this->createPerson();
        DB::connection()->commit();
        $resultFile = tempnam(sys_get_temp_dir(), 'tapoda-context-evidence-race-');
        self::assertNotFalse($resultFile);
        $children = [];

        try {
            foreach ([1, 2] as $attempt) {
                $pid = pcntl_fork();
                self::assertNotSame(-1, $pid);

                if ($pid === 0) {
                    $connection = $this->newChildPostgresConnection();

                    try {
                        $statement = $connection->prepare(<<<'SQL'
INSERT INTO person_application_context_evidence (
    id,
    person_id,
    version,
    facts_encrypted,
    encryption_key_version,
    effective_at,
    stale_at,
    created_at
) VALUES (?, ?, 1, 'race-fixture', 'v1', ?, NULL, ?)
SQL);
                        $at = '2026-01-01T00:00:00+00:00';
                        $statement->execute([
                            sprintf('57000000-0000-4000-8000-%012d', $attempt),
                            $personId,
                            $at,
                            $at,
                        ]);
                        file_put_contents($resultFile, "inserted\n", FILE_APPEND | LOCK_EX);
                    } catch (\PDOException $exception) {
                        file_put_contents(
                            $resultFile,
                            "rejected:{$exception->getCode()}\n",
                            FILE_APPEND | LOCK_EX,
                        );
                    }

                    posix_kill(posix_getpid(), SIGKILL);
                }

                $children[] = $pid;
            }

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertTrue(pcntl_wifsignaled($status));
            }
            $children = [];

            DB::purge();
            DB::reconnect();
            $results = file($resultFile, FILE_IGNORE_NEW_LINES);
            self::assertIsArray($results);
            sort($results);
            self::assertSame(['inserted', 'rejected:P0001'], $results);
            self::assertSame(
                1,
                DB::table('person_application_context_evidence')
                    ->where('person_id', $personId)
                    ->where('version', 1)
                    ->count(),
            );
        } finally {
            foreach ($children as $pid) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
            if (is_file($resultFile)) {
                unlink($resultFile);
            }
            DB::purge();
            DB::reconnect();
            DB::statement('TRUNCATE person_application_context_evidence');
            DB::table('people')->where('id', $personId)->delete();
            DB::connection()->beginTransaction();
        }
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidEvidenceRows(): iterable
    {
        yield 'zero version' => [
            ['version' => 0],
            'must start at version 1',
        ];
        yield 'empty interval' => [
            ['stale_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z')],
            'person_context_evidence_interval_valid',
        ];
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidSuccessorRows(): iterable
    {
        yield 'skipped version' => [
            ['version' => 3],
            'version must be monotonic',
        ];
        yield 'non-increasing effective time' => [
            [
                'effective_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
                'stale_at' => CarbonImmutable::parse('2026-02-01T00:00:00Z'),
            ],
            'effective time must be monotonic',
        ];
        yield 'overlapping interval' => [
            [
                'effective_at' => CarbonImmutable::parse('2026-05-01T00:00:00Z'),
                'stale_at' => CarbonImmutable::parse('2026-07-01T00:00:00Z'),
            ],
            'intervals must not overlap',
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenMutations(): iterable
    {
        yield 'update' => ['update'];
        yield 'delete' => ['delete'];
        yield 'reparent' => ['reparent'];
    }

    /** @return array<string, mixed> */
    private function evidenceRow(string $personId): array
    {
        return [
            'id' => '57000000-0000-4000-8000-000000000001',
            'person_id' => $personId,
            'version' => 1,
            'facts_encrypted' => 'persistence-fixture',
            'encryption_key_version' => 'v1',
            'effective_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
            'stale_at' => CarbonImmutable::parse('2027-01-01T00:00:00Z'),
            'created_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
        ];
    }

    private function createPerson(): string
    {
        $personId = (string) Str::uuid();
        DB::table('people')->insert([
            'id' => $personId,
            'given_name' => 'ทดสอบ',
            'family_name' => 'ฐานข้อมูล',
            'created_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
            'updated_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z'),
        ]);

        return $personId;
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            if (getenv('REQUIRE_REAL_SERVICES') === '1') {
                self::fail('REQUIRE_REAL_SERVICES=1 requires PostgreSQL evidence guards.');
            }

            self::markTestSkipped('Application context evidence guards are PostgreSQL-specific.');
        }
    }

    private function newChildPostgresConnection(): \PDO
    {
        $configuration = config('database.connections.pgsql');
        self::assertIsArray($configuration);
        $host = (string) ($configuration['host'] ?? '');
        $port = (string) ($configuration['port'] ?? '5432');
        $database = (string) ($configuration['database'] ?? '');

        return new \PDO(
            "pgsql:host={$host};port={$port};dbname={$database}",
            (string) ($configuration['username'] ?? ''),
            (string) ($configuration['password'] ?? ''),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
    }
}
