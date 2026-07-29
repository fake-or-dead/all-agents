<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalIdentityAccessSeeder extends Seeder
{
    private const string PersonId = '20000000-0000-4000-8000-000000000001';

    private const string IdentifierId = '20000000-0000-4000-8000-000000000002';

    private const string AccountId = '20000000-0000-4000-8000-000000000003';

    private const string Email = 'local-seed-account@tapoda.test';

    private const string Identifier = '1234567890123';

    private const string PasswordHash = '$2y$12$Cm6dfy0iKu9rmZE34NABqeoVtWJZqgOEKpWCAzKVHTAg85sCxI.qi';

    public function run(): void
    {
        $this->assertLocalFixtureRuntime();

        $now = CarbonImmutable::parse('2026-07-29T00:00:00+07:00');
        [$peopleKeyVersion, $peopleLookupKey] = $this->currentPeopleLookupKey();
        [$accountKeyVersion, $accountLookupKey] = $this->currentAccountLookupKey();

        DB::transaction(function () use (
            $accountKeyVersion,
            $accountLookupKey,
            $now,
            $peopleKeyVersion,
            $peopleLookupKey,
        ): void {
            if (DB::table('accounts')->where('id', self::AccountId)->exists()) {
                return;
            }

            DB::table('people')->insert([
                'id' => self::PersonId,
                'given_name' => 'ผู้ทดสอบ',
                'family_name' => 'ระบบภายใน',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('person_identifiers')->insert([
                'id' => self::IdentifierId,
                'person_id' => self::PersonId,
                'type' => 'personal_id',
                'country_code' => 'TH',
                'identifier_encrypted' => Crypt::encrypt(self::Identifier),
                'lookup_key_version' => $peopleKeyVersion,
                'lookup_digest' => hash_hmac(
                    'sha256',
                    'personal_id:TH:'.self::Identifier,
                    $peopleLookupKey,
                ),
                'last_four' => '0123',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('accounts')->insert([
                'id' => self::AccountId,
                'person_id' => self::PersonId,
                'email_digest_key_version' => $accountKeyVersion,
                'email_digest' => hash_hmac(
                    'sha256',
                    'email:'.self::Email,
                    $accountLookupKey,
                ),
                'email_encrypted' => Crypt::encrypt(self::Email),
                'status' => 'active',
                'credential_epoch' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('credentials')->insert([
                'account_id' => self::AccountId,
                'password_hash' => self::PasswordHash,
                'algorithm' => 'current',
                'changed_at' => $now,
            ]);
        });
    }

    private function assertLocalFixtureRuntime(): void
    {
        $application = app(Application::class);
        $config = app(Repository::class);

        if (
            ! $application->environment(['local', 'testing'])
            || $config->get('identity-access.verification_adapter') !== 'deterministic-fake'
        ) {
            throw new RuntimeException(
                'Local IdentityAccess fixtures require local/testing with the deterministic-fake verification adapter.',
            );
        }
    }

    /** @return array{string, string} */
    private function currentPeopleLookupKey(): array
    {
        return $this->currentLookupKey(
            'people.identifier_lookup_key_version',
            'people.identifier_lookup_keys',
        );
    }

    /** @return array{string, string} */
    private function currentAccountLookupKey(): array
    {
        return $this->currentLookupKey(
            'identity-access.account_lookup_key_version',
            'identity-access.account_lookup_keys',
        );
    }

    /** @return array{string, string} */
    private function currentLookupKey(string $versionPath, string $keysPath): array
    {
        $config = app(Repository::class);
        $version = $config->get($versionPath);
        $keys = $config->get($keysPath);

        if (! is_string($version) || ! is_array($keys)) {
            throw new RuntimeException("Missing local seed lookup key for {$versionPath}.");
        }

        $key = $keys[$version] ?? null;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException("Missing local seed lookup key for {$versionPath}.");
        }

        return [$version, $key];
    }
}
