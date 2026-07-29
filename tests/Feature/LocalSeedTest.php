<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class LocalSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_like_runtime_rejects_local_identity_fixture_before_any_write(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('identity-access.verification_adapter', 'deterministic-fake');

        try {
            $this->app->make(DatabaseSeeder::class)->run();
            $this->fail('Production-like configuration must reject local identity fixtures.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Local IdentityAccess fixtures require local/testing with the deterministic-fake verification adapter.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('person_identifiers', 0);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('credentials', 0);
    }

    public function test_testing_runtime_rejects_non_deterministic_identity_adapter_before_any_write(): void
    {
        config()->set('identity-access.verification_adapter', 'disabled');

        try {
            $this->app->make(DatabaseSeeder::class)->run();
            $this->fail('A non-deterministic verifier must reject local identity fixtures.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Local IdentityAccess fixtures require local/testing with the deterministic-fake verification adapter.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('person_identifiers', 0);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('credentials', 0);
    }

    public function test_local_seed_creates_one_canonical_account_fixture_without_reowning_consent_documents(): void
    {
        $this->seed();

        $person = DB::table('people')->sole();
        $account = DB::table('accounts')->sole();
        $identifier = DB::table('person_identifiers')->sole();
        $credential = DB::table('credentials')->sole();

        $this->assertSame('20000000-0000-4000-8000-000000000001', $person->id);
        $this->assertSame('ผู้ทดสอบ', $person->given_name);
        $this->assertSame('ระบบภายใน', $person->family_name);
        $this->assertSame($person->id, $account->person_id);
        $this->assertSame('local-seed-account@tapoda.test', Crypt::decrypt($account->email_encrypted));
        $this->assertSame($person->id, $identifier->person_id);
        $this->assertSame('personal_id', $identifier->type);
        $this->assertSame('1234567890123', Crypt::decrypt($identifier->identifier_encrypted));
        $this->assertSame($account->id, $credential->account_id);
        $this->assertTrue(Hash::check('TapodaLocalSeed!2026', $credential->password_hash));

        $this->assertDatabaseCount('consent_documents', 2);
        $this->assertDatabaseHas('consent_document_versions', [
            'id' => '10000000-0000-4000-8000-000000000002',
            'version_label' => 'local-fixture-v1',
        ]);
        $this->assertDatabaseHas('consent_document_versions', [
            'id' => '30000000-0000-4000-8000-000000000002',
            'version_label' => 'local-application-v1',
            'status' => 'published',
        ]);
        $this->assertDatabaseCount('consent_acceptances', 0);
    }

    public function test_seeded_identity_can_sign_in_through_the_supported_public_flow(): void
    {
        $this->seed();

        $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '1234567890123',
            'password' => 'TapodaLocalSeed!2026',
        ])->assertOk()->assertJsonPath('redirect', '/account');

        $this->assertAuthenticated();
    }
}
