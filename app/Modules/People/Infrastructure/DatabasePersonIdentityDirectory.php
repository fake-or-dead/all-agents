<?php

namespace App\Modules\People\Infrastructure;

use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DatabasePersonIdentityDirectory implements PersonIdentityDirectory
{
    public function __construct(
        private ConnectionInterface $database,
        private Encrypter $encrypter,
        private Repository $config,
    ) {}

    public function create(
        IdentityClaim $identity,
        string $givenName,
        string $familyName,
    ): string {
        $personId = (string) Str::uuid();
        $now = CarbonImmutable::now();
        $keyVersion = $this->currentLookupKeyVersion();

        $this->database->table('people')->insert([
            'id' => $personId,
            'given_name' => $givenName,
            'family_name' => $familyName,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->database->table('person_identifiers')->insert([
            'id' => (string) Str::uuid(),
            'person_id' => $personId,
            'type' => $identity->type,
            'country_code' => $identity->countryCode,
            'identifier_encrypted' => $this->encrypter->encrypt(
                $identity->normalizedIdentifier,
            ),
            'lookup_key_version' => $keyVersion,
            'lookup_digest' => $this->lookupDigest($identity, $keyVersion),
            'last_four' => mb_substr($identity->normalizedIdentifier, -4),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $personId;
    }

    public function claimForAccount(
        IdentityClaim $identity,
        string $givenName,
        string $familyName,
        ?string $ownershipProof,
        CarbonImmutable $now,
    ): ?string {
        $personId = $this->personIdForIdentityForUpdate($identity);

        if ($personId === null) {
            return $this->create($identity, $givenName, $familyName);
        }

        if (! is_string($ownershipProof) || strlen($ownershipProof) < 32) {
            return null;
        }

        $consumed = $this->database
            ->table('person_account_link_proofs')
            ->where('person_id', $personId)
            ->where('token_digest', hash('sha256', $ownershipProof))
            ->where('expires_at', '>', $now)
            ->whereNull('consumed_at')
            ->lockForUpdate()
            ->update([
                'consumed_at' => $now,
                'updated_at' => $now,
            ]);

        return $consumed === 1 ? $personId : null;
    }

    public function approveAccountLink(
        IdentityClaim $identity,
        CarbonImmutable $expiresAt,
    ): ?string {
        $personId = $this->personIdForIdentity($identity);

        if ($personId === null) {
            return null;
        }

        $token = Str::random(64);
        $now = CarbonImmutable::now();
        $this->database->table('person_account_link_proofs')->insert([
            'id' => (string) Str::uuid(),
            'person_id' => $personId,
            'token_digest' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'consumed_at' => null,
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $token;
    }

    public function personIdForIdentity(IdentityClaim $identity): ?string
    {
        $personId = $this->personIdForIdentityQuery($identity)->value('person_id');

        return is_string($personId) ? $personId : null;
    }

    private function personIdForIdentityForUpdate(IdentityClaim $identity): ?string
    {
        $personId = $this->personIdForIdentityQuery($identity)
            ->lockForUpdate()
            ->value('person_id');

        return is_string($personId) ? $personId : null;
    }

    private function personIdForIdentityQuery(IdentityClaim $identity): Builder
    {
        $query = $this->database->table('person_identifiers');
        foreach ($this->lookupKeys() as $version => $key) {
            $query->orWhere(function ($candidate) use ($identity, $key, $version): void {
                $candidate
                    ->where('lookup_key_version', $version)
                    ->where('lookup_digest', $this->digestWithKey($identity, $key));
            });
        }

        return $query;
    }

    public function identityExists(IdentityClaim $identity): bool
    {
        return $this->personIdForIdentity($identity) !== null;
    }

    public function rateLimitPseudonym(IdentityClaim $identity): string
    {
        $version = (string) $this->config->get('identity-access.rate_limit_key_version');
        $keys = $this->config->get('identity-access.rate_limit_keys');
        $key = is_array($keys) ? ($keys[$version] ?? null) : null;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Missing People rate-limit pseudonym key.');
        }

        return "{$version}:".hash_hmac(
            'sha256',
            implode(':', [
                'person-identity',
                $identity->type,
                $identity->countryCode,
                $identity->normalizedIdentifier,
            ]),
            $key,
        );
    }

    private function lookupDigest(IdentityClaim $identity, string $version): string
    {
        $keys = $this->lookupKeys();

        return $this->digestWithKey($identity, $keys[$version]);
    }

    private function digestWithKey(IdentityClaim $identity, string $key): string
    {
        return hash_hmac(
            'sha256',
            implode(':', [
                $identity->type,
                $identity->countryCode,
                $identity->normalizedIdentifier,
            ]),
            $key,
        );
    }

    private function currentLookupKeyVersion(): string
    {
        $version = $this->config->get('people.identifier_lookup_key_version');

        if (! is_string($version) || ! array_key_exists($version, $this->lookupKeys())) {
            throw new RuntimeException('Missing current People lookup key.');
        }

        return $version;
    }

    /** @return array<string, string> */
    private function lookupKeys(): array
    {
        $keys = $this->config->get('people.identifier_lookup_keys');

        if (! is_array($keys) || $keys === []) {
            throw new RuntimeException('Missing People lookup keys.');
        }

        return array_filter(
            $keys,
            static fn (mixed $key, mixed $version): bool => is_string($version)
                && $version !== ''
                && is_string($key)
                && $key !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
