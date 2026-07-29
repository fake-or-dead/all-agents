<?php

namespace Tests\Feature\People;

use Database\Seeders\LocalApplicationContextEvidenceSeeder;
use Database\Seeders\LocalIdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class ApplicationContextEvidenceFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_fixture_is_idempotent_and_persists_no_raw_birth_or_age_lookup(): void
    {
        $this->seed(LocalIdentityAccessSeeder::class);
        $this->seed(LocalApplicationContextEvidenceSeeder::class);
        $firstCiphertext = DB::table('person_application_context_evidence')
            ->sole()
            ->facts_encrypted;

        $this->seed(LocalApplicationContextEvidenceSeeder::class);

        self::assertSame(1, DB::table('person_application_context_evidence')->count());
        $stored = DB::table('person_application_context_evidence')->sole();
        self::assertSame($firstCiphertext, $stored->facts_encrypted);
        self::assertStringNotContainsString(
            '1990-06-15',
            json_encode($stored, JSON_THROW_ON_ERROR),
        );
        self::assertSame([
            'id',
            'person_id',
            'version',
            'facts_encrypted',
            'encryption_key_version',
            'effective_at',
            'stale_at',
            'created_at',
        ], Schema::getColumnListing('person_application_context_evidence'));
    }

    public function test_production_like_runtime_rejects_fixture_before_evidence_write(): void
    {
        $this->seed(LocalIdentityAccessSeeder::class);
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('identity-access.verification_adapter', 'deterministic-fake');

        try {
            $this->app->call([
                $this->app->make(LocalApplicationContextEvidenceSeeder::class),
                'run',
            ]);
            self::fail('Production-like runtime must reject application context fixtures.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Application context evidence fixtures require local deterministic-fake mode.',
                $exception->getMessage(),
            );
        }

        self::assertSame(0, DB::table('person_application_context_evidence')->count());
    }
}
