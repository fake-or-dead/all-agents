<?php

namespace Tests\Feature\FormEngine;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('service-integration')]
final class FormPublicationImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $replacement
     */
    #[DataProvider('publishedRecordMutations')]
    public function test_postgresql_rejects_mutating_published_form_records(
        string $table,
        string $id,
        array $replacement,
    ): void {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);

        $this->expectException(QueryException::class);
        DB::table($table)->where('id', $id)->update($replacement);
    }

    #[DataProvider('publishedRecords')]
    public function test_postgresql_rejects_deleting_published_form_records(
        string $table,
        string $id,
    ): void {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);

        $this->expectException(QueryException::class);
        DB::table($table)->where('id', $id)->delete();
    }

    public function test_postgresql_rejects_extending_a_published_form_version(): void
    {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);

        $this->expectException(QueryException::class);
        DB::table('form_options')->insert([
            'id' => '30000000-0000-4000-8000-000000000499',
            'field_id' => '30000000-0000-4000-8000-000000000302',
            'semantic_key' => 'later',
            'value' => 'later',
            'label_th' => 'เพิ่มภายหลัง',
            'display_order' => 99,
            'created_at' => now(),
        ]);
    }

    public function test_postgresql_rejects_inserting_a_version_as_published(): void
    {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);

        $this->expectException(QueryException::class);
        DB::table('form_versions')->insert([
            'id' => '30000000-0000-4000-8000-000000000799',
            'definition_id' => '30000000-0000-4000-8000-000000000101',
            'version_number' => 99,
            'locale' => 'th',
            'status' => 'published',
            'schema_checksum' => str_repeat('a', 64),
            'consent_document_version_id' => '30000000-0000-4000-8000-000000000002',
            'consent_document_checksum' => str_repeat('b', 64),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_publication_waits_for_an_inflight_child_mutation(): void
    {
        $this->assertPublicationWaitsForInflightMutation(
            static function (): void {
                DB::table('form_sections')
                    ->where('id', '30000000-0000-4000-8000-000000000701')
                    ->update(['title_th' => 'แก้ไขก่อนเผยแพร่']);
            },
            static function (): void {
                self::assertSame(
                    'แก้ไขก่อนเผยแพร่',
                    DB::table('form_sections')
                        ->where('id', '30000000-0000-4000-8000-000000000701')
                        ->value('title_th'),
                );
            },
        );
    }

    public function test_postgresql_publication_waits_for_an_inflight_definition_mutation(): void
    {
        $this->assertPublicationWaitsForInflightMutation(
            static function (): void {
                DB::table('form_definitions')
                    ->where('id', '30000000-0000-4000-8000-000000000798')
                    ->update(['title_th' => 'แก้ไขก่อนเผยแพร่']);
            },
            static function (): void {
                self::assertSame(
                    'แก้ไขก่อนเผยแพร่',
                    DB::table('form_definitions')
                        ->where('id', '30000000-0000-4000-8000-000000000798')
                        ->value('title_th'),
                );
            },
        );
    }

    public function test_postgresql_inflight_publication_blocks_then_rejects_child_mutation(): void
    {
        $this->assertInflightPublicationRejectsMutation(
            <<<'SQL'
UPDATE form_sections
SET title_th = 'ห้ามแก้หลังเผยแพร่'
WHERE id = '30000000-0000-4000-8000-000000000701'
SQL,
            'published form records are immutable',
            static function (): void {
                self::assertSame(
                    'ส่วนร่าง',
                    DB::table('form_sections')
                        ->where('id', '30000000-0000-4000-8000-000000000701')
                        ->value('title_th'),
                );
            },
        );
    }

    public function test_postgresql_inflight_publication_blocks_then_rejects_definition_mutation(): void
    {
        $this->assertInflightPublicationRejectsMutation(
            <<<'SQL'
UPDATE form_definitions
SET title_th = 'ห้ามแก้หลังเผยแพร่'
WHERE id = '30000000-0000-4000-8000-000000000798'
SQL,
            'definitions with published form versions are immutable',
            static function (): void {
                self::assertSame(
                    'แบบฟอร์มทดสอบพร้อมกัน',
                    DB::table('form_definitions')
                        ->where('id', '30000000-0000-4000-8000-000000000798')
                        ->value('title_th'),
                );
            },
        );
    }

    public function test_postgresql_rejects_reparenting_a_draft_section_into_a_published_version(): void
    {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);
        $this->createDraftVersionAndSection();

        $this->expectException(QueryException::class);
        DB::table('form_sections')
            ->where('id', '30000000-0000-4000-8000-000000000701')
            ->update(['form_version_id' => '30000000-0000-4000-8000-000000000102']);
    }

    public function test_postgresql_rejects_reparenting_a_draft_field_into_a_published_version(): void
    {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);
        $this->createDraftVersionAndSection();
        $this->createDraftField();

        $this->expectException(QueryException::class);
        DB::table('form_fields')
            ->where('id', '30000000-0000-4000-8000-000000000702')
            ->update([
                'form_version_id' => '30000000-0000-4000-8000-000000000102',
                'section_id' => '30000000-0000-4000-8000-000000000201',
            ]);
    }

    public function test_postgresql_rejects_reparenting_a_draft_option_into_a_published_field(): void
    {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);
        $this->createDraftVersionAndSection();
        $this->createDraftField();
        DB::table('form_options')->insert([
            'id' => '30000000-0000-4000-8000-000000000703',
            'field_id' => '30000000-0000-4000-8000-000000000702',
            'semantic_key' => 'draft-option',
            'value' => 'draft-option',
            'label_th' => 'ตัวเลือกร่าง',
            'display_order' => 1,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('form_options')
            ->where('id', '30000000-0000-4000-8000-000000000703')
            ->update(['field_id' => '30000000-0000-4000-8000-000000000302']);
    }

    #[DataProvider('invalidPublicationStates')]
    public function test_postgresql_rejects_inconsistent_publication_state(
        string $status,
        ?string $publishedAt,
        int $versionNumber,
    ): void {
        $this->requirePostgres();
        $this->seed(DatabaseSeeder::class);

        $this->expectException(QueryException::class);
        DB::table('form_versions')->insert([
            'id' => "30000000-0000-4000-8000-00000000070{$versionNumber}",
            'definition_id' => '30000000-0000-4000-8000-000000000101',
            'version_number' => $versionNumber,
            'locale' => 'th',
            'status' => $status,
            'schema_checksum' => str_repeat('c', 64),
            'consent_document_version_id' => '30000000-0000-4000-8000-000000000002',
            'consent_document_checksum' => str_repeat('d', 64),
            'published_at' => $publishedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function publishedRecordMutations(): iterable
    {
        yield 'definition' => [
            'form_definitions',
            '30000000-0000-4000-8000-000000000101',
            ['title_th' => 'rewritten'],
        ];
        yield 'version' => [
            'form_versions',
            '30000000-0000-4000-8000-000000000102',
            ['version_number' => 2],
        ];
        yield 'section' => [
            'form_sections',
            '30000000-0000-4000-8000-000000000201',
            ['title_th' => 'เปลี่ยน'],
        ];
        yield 'field' => [
            'form_fields',
            '30000000-0000-4000-8000-000000000301',
            ['label_th' => 'เปลี่ยน'],
        ];
        yield 'option' => [
            'form_options',
            '30000000-0000-4000-8000-000000000401',
            ['label_th' => 'เปลี่ยน'],
        ];
        yield 'assignment' => [
            'form_assignments',
            '30000000-0000-4000-8000-000000000501',
            ['locale' => 'en'],
        ];
        yield 'publication event' => [
            'form_publication_events',
            '30000000-0000-4000-8000-000000000601',
            ['reason' => 'rewritten'],
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function publishedRecords(): iterable
    {
        foreach (self::publishedRecordMutations() as $name => [$table, $id]) {
            yield $name => [$table, $id];
        }
    }

    /**
     * @return iterable<string, array{string, string|null, int}>
     */
    public static function invalidPublicationStates(): iterable
    {
        yield 'published without timestamp' => ['published', null, 3];
        yield 'draft with timestamp' => ['draft', '2026-07-29 00:00:00+07', 4];
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            if (getenv('REQUIRE_REAL_SERVICES') === '1') {
                self::fail('REQUIRE_REAL_SERVICES=1 requires PostgreSQL for FormEngine guards.');
            }

            self::markTestSkipped('FormEngine immutability guards are PostgreSQL-specific.');
        }
    }

    /**
     * @param  callable(): void  $mutation
     * @param  callable(): void  $assertion
     */
    private function assertPublicationWaitsForInflightMutation(
        callable $mutation,
        callable $assertion,
    ): void {
        $this->requirePostgres();

        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            self::markTestSkipped('pcntl/posix is required for PostgreSQL concurrency proof.');
        }

        $this->createCommittedConcurrencyFixture();
        $resultFile = tempnam(sys_get_temp_dir(), 'tapoda-form-publication-');
        $startedFile = tempnam(sys_get_temp_dir(), 'tapoda-form-publication-started-');
        self::assertNotFalse($resultFile);
        self::assertNotFalse($startedFile);
        unlink($startedFile);
        $pid = null;

        try {
            DB::beginTransaction();
            $mutation();
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                $connection = $this->newChildPostgresConnection();
                $backendPid = $connection->query('SELECT pg_backend_pid()')
                    ->fetchColumn();
                file_put_contents($startedFile, (string) $backendPid, LOCK_EX);

                try {
                    $connection->exec(<<<'SQL'
UPDATE form_versions
SET status = 'published',
    published_at = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
WHERE id = '30000000-0000-4000-8000-000000000700'
SQL);
                    file_put_contents($resultFile, 'published', LOCK_EX);
                } catch (\Throwable $exception) {
                    file_put_contents(
                        $resultFile,
                        'error:'.$exception->getMessage(),
                        LOCK_EX,
                    );
                }

                posix_kill(posix_getpid(), SIGKILL);
            }

            $this->assertBackendWaitsOnLock($startedFile);
            self::assertSame('', (string) file_get_contents($resultFile));
            DB::commit();
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifsignaled($status));
            $pid = null;
            DB::purge();
            DB::reconnect();
            self::assertSame('published', (string) file_get_contents($resultFile));
            self::assertSame(
                'published',
                DB::table('form_versions')
                    ->where('id', '30000000-0000-4000-8000-000000000700')
                    ->value('status'),
            );
            $assertion();
        } finally {
            if (DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }
            if (is_int($pid)) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
            if (is_file($resultFile)) {
                unlink($resultFile);
            }
            if (is_file($startedFile)) {
                unlink($startedFile);
            }
            $this->cleanupCommittedConcurrencyFixture();
            DB::connection()->beginTransaction();
        }
    }

    /**
     * @param  callable(): void  $assertion
     */
    private function assertInflightPublicationRejectsMutation(
        string $mutationSql,
        string $expectedError,
        callable $assertion,
    ): void {
        $this->requirePostgres();

        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            self::markTestSkipped('pcntl/posix is required for PostgreSQL concurrency proof.');
        }

        $this->createCommittedConcurrencyFixture();
        $resultFile = tempnam(sys_get_temp_dir(), 'tapoda-form-mutation-');
        $startedFile = tempnam(sys_get_temp_dir(), 'tapoda-form-mutation-started-');
        self::assertNotFalse($resultFile);
        self::assertNotFalse($startedFile);
        unlink($startedFile);
        $pid = null;

        try {
            DB::beginTransaction();
            DB::table('form_versions')
                ->where('id', '30000000-0000-4000-8000-000000000700')
                ->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                $connection = $this->newChildPostgresConnection();
                $backendPid = $connection->query('SELECT pg_backend_pid()')
                    ->fetchColumn();
                file_put_contents($startedFile, (string) $backendPid, LOCK_EX);

                try {
                    $connection->exec($mutationSql);
                    file_put_contents($resultFile, 'mutated', LOCK_EX);
                } catch (\PDOException $exception) {
                    file_put_contents(
                        $resultFile,
                        "rejected:{$exception->getCode()}:{$exception->getMessage()}",
                        LOCK_EX,
                    );
                }

                posix_kill(posix_getpid(), SIGKILL);
            }

            $this->assertBackendWaitsOnLock($startedFile);
            self::assertSame('', (string) file_get_contents($resultFile));
            DB::commit();
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifsignaled($status));
            $pid = null;
            DB::purge();
            DB::reconnect();
            $result = (string) file_get_contents($resultFile);
            self::assertStringStartsWith('rejected:P0001:', $result);
            self::assertStringContainsString($expectedError, $result);
            $assertion();
        } finally {
            if (DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }
            if (is_int($pid)) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
            if (is_file($resultFile)) {
                unlink($resultFile);
            }
            if (is_file($startedFile)) {
                unlink($startedFile);
            }
            $this->cleanupCommittedConcurrencyFixture();
            DB::connection()->beginTransaction();
        }
    }

    private function createCommittedConcurrencyFixture(): void
    {
        $consentChecksum = DB::table('consent_document_versions')
            ->where('id', '10000000-0000-4000-8000-000000000002')
            ->value('content_checksum');
        self::assertIsString($consentChecksum);
        $now = now();
        DB::table('form_definitions')->insert([
            'id' => '30000000-0000-4000-8000-000000000798',
            'form_key' => 'concurrency_fixture',
            'title_th' => 'แบบฟอร์มทดสอบพร้อมกัน',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->createDraftVersionAndSection(
            '30000000-0000-4000-8000-000000000798',
            '10000000-0000-4000-8000-000000000002',
            $consentChecksum,
        );
        DB::connection()->commit();
    }

    private function assertBackendWaitsOnLock(string $startedFile): void
    {
        $backendPid = 0;

        for ($attempt = 0; $attempt < 200; $attempt++) {
            if (is_file($startedFile)) {
                $backendPid = (int) file_get_contents($startedFile);
            }

            if ($backendPid > 0) {
                break;
            }

            usleep(10_000);
        }

        self::assertFileExists($startedFile);
        self::assertGreaterThan(0, $backendPid);
        $waiting = false;

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $activity = DB::selectOne(
                <<<'SQL'
SELECT state, wait_event_type
FROM pg_stat_activity
WHERE pid = ?
SQL,
                [$backendPid],
            );

            if (
                $activity !== null
                && $activity->state === 'active'
                && $activity->wait_event_type === 'Lock'
            ) {
                $waiting = true;
                break;
            }

            usleep(10_000);
        }

        self::assertTrue($waiting, 'Child PostgreSQL backend never waited on the publication lock.');
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

    private function cleanupCommittedConcurrencyFixture(): void
    {
        DB::purge();
        DB::reconnect();
        DB::unprepared(<<<'SQL'
TRUNCATE
    form_publication_events,
    form_assignments,
    form_options,
    form_fields,
    form_sections,
    form_versions,
    form_definitions
SQL);
    }

    private function createDraftVersionAndSection(
        string $definitionId = '30000000-0000-4000-8000-000000000101',
        string $consentVersionId = '30000000-0000-4000-8000-000000000002',
        string $consentChecksum = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    ): void {
        DB::table('form_versions')->insert([
            'id' => '30000000-0000-4000-8000-000000000700',
            'definition_id' => $definitionId,
            'version_number' => 2,
            'locale' => 'th',
            'status' => 'draft',
            'schema_checksum' => str_repeat('a', 64),
            'consent_document_version_id' => $consentVersionId,
            'consent_document_checksum' => $consentChecksum,
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('form_sections')->insert([
            'id' => '30000000-0000-4000-8000-000000000701',
            'form_version_id' => '30000000-0000-4000-8000-000000000700',
            'semantic_key' => 'draft-section',
            'title_th' => 'ส่วนร่าง',
            'description_th' => null,
            'display_order' => 1,
            'created_at' => now(),
        ]);
    }

    private function createDraftField(): void
    {
        DB::table('form_fields')->insert([
            'id' => '30000000-0000-4000-8000-000000000702',
            'form_version_id' => '30000000-0000-4000-8000-000000000700',
            'section_id' => '30000000-0000-4000-8000-000000000701',
            'semantic_key' => 'draft.field',
            'field_type' => 'short_text',
            'label_th' => 'ช่องร่าง',
            'help_text_th' => null,
            'placeholder_th' => null,
            'required_rule' => json_encode(false, JSON_THROW_ON_ERROR),
            'validation_rules' => json_encode([], JSON_THROW_ON_ERROR),
            'visibility_rule' => null,
            'hidden_answer_policy' => 'retain',
            'renderer_hint' => null,
            'initial_value' => null,
            'consent_document_version_id' => null,
            'consent_document_checksum' => null,
            'display_order' => 1,
            'created_at' => now(),
        ]);
    }
}
