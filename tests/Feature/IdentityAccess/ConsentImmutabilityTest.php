<?php

namespace Tests\Feature\IdentityAccess;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConsentImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_rejects_updating_published_consent(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('Immutable consent guards are PostgreSQL-specific.');
        }

        $this->expectException(QueryException::class);
        DB::table('consent_document_versions')
            ->where('id', '10000000-0000-4000-8000-000000000002')
            ->update(['content' => 'rewritten']);
    }

    public function test_postgresql_rejects_deleting_published_consent(): void
    {
        $this->requirePostgres();

        $this->expectException(QueryException::class);
        DB::table('consent_document_versions')
            ->where('id', '10000000-0000-4000-8000-000000000002')
            ->delete();
    }

    public function test_postgresql_rejects_updating_acceptance_evidence(): void
    {
        $acceptanceId = $this->createAcceptance();

        $this->expectException(QueryException::class);
        DB::table('consent_acceptances')->where('id', $acceptanceId)->update([
            'context' => 'rewritten',
        ]);
    }

    public function test_postgresql_rejects_deleting_acceptance_evidence(): void
    {
        $acceptanceId = $this->createAcceptance();

        $this->expectException(QueryException::class);
        DB::table('consent_acceptances')->where('id', $acceptanceId)->delete();
    }

    private function createAcceptance(): string
    {
        $this->requirePostgres();

        $personId = '10000000-0000-4000-8000-000000000099';
        $acceptanceId = '10000000-0000-4000-8000-000000000098';
        DB::table('people')->insert([
            'id' => $personId,
            'given_name' => 'Guard',
            'family_name' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('consent_acceptances')->insert([
            'id' => $acceptanceId,
            'person_id' => $personId,
            'document_version_id' => '10000000-0000-4000-8000-000000000002',
            'document_checksum' => str_repeat('a', 64),
            'locale' => 'th',
            'context' => 'registration',
            'evidence' => json_encode(['method' => 'test']),
            'accepted_at' => now(),
        ]);

        return $acceptanceId;
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('Immutable consent guards are PostgreSQL-specific.');
        }
    }
}
