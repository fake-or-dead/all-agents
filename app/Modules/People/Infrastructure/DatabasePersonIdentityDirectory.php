<?php

namespace App\Modules\People\Infrastructure;

use App\Modules\People\Contracts\PersonIdentityDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabasePersonIdentityDirectory implements PersonIdentityDirectory
{
    public function __construct(
        private ConnectionInterface $database,
        private Encrypter $encrypter,
    ) {}

    public function create(
        string $type,
        string $countryCode,
        string $normalizedIdentifier,
        string $lookupDigest,
        string $givenName,
        string $familyName,
    ): string {
        $personId = (string) Str::uuid();
        $now = CarbonImmutable::now();

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
            'type' => $type,
            'country_code' => $countryCode,
            'identifier_encrypted' => $this->encrypter->encrypt($normalizedIdentifier),
            'lookup_digest' => $lookupDigest,
            'last_four' => mb_substr($normalizedIdentifier, -4),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $personId;
    }

    public function personIdForIdentifier(string $lookupDigest): ?string
    {
        $personId = $this->database
            ->table('person_identifiers')
            ->where('lookup_digest', $lookupDigest)
            ->value('person_id');

        return is_string($personId) ? $personId : null;
    }

    public function identifierExists(string $lookupDigest): bool
    {
        return $this->database
            ->table('person_identifiers')
            ->where('lookup_digest', $lookupDigest)
            ->exists();
    }
}
