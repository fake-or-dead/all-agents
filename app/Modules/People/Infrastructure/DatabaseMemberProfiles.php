<?php

namespace App\Modules\People\Infrastructure;

use App\Modules\People\Contracts\MemberProfiles;
use App\Modules\People\Data\AddressUpdate;
use App\Modules\People\Data\MemberProfileView;
use App\Modules\People\Data\MutationResult;
use App\Modules\People\Data\ProfileUpdate;
use App\Modules\People\Data\TrainingUpdate;
use App\Modules\ReferenceData\Contracts\ReferenceData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseMemberProfiles implements MemberProfiles
{
    public function __construct(
        private ConnectionInterface $database,
        private Encrypter $encrypter,
        private ReferenceData $references,
    ) {}

    public function profileFor(string $personId): ?MemberProfileView
    {
        if (! Str::isUuid($personId)) {
            return null;
        }

        $person = $this->database->table('people')->where('id', $personId)->first();
        if ($person === null) {
            return null;
        }

        $identifier = $this->database
            ->table('person_identifiers')
            ->where('person_id', $personId)
            ->orderBy('created_at')
            ->first(['type', 'country_code', 'last_four']);
        if ($identifier === null) {
            return null;
        }

        $contact = $this->database
            ->table('person_contacts')
            ->where('person_id', $personId)
            ->first();
        $address = $this->database
            ->table('person_addresses')
            ->where('person_id', $personId)
            ->first();

        return new MemberProfileView(
            personId: (string) $person->id,
            givenName: (string) $person->given_name,
            familyName: (string) $person->family_name,
            version: (int) $person->profile_version,
            identifier: [
                'type' => (string) $identifier->type,
                'countryCode' => (string) $identifier->country_code,
                'masked' => str_repeat('•', 9).(string) $identifier->last_four,
            ],
            contact: [
                'email' => $this->decryptNullable($contact?->email_encrypted),
                'phone' => $this->decryptNullable($contact?->phone_encrypted),
                'version' => $contact === null ? 0 : (int) $contact->version,
            ],
            address: $address === null ? null : [
                'addressLine1' => $this->decryptNullable($address->address_line_1_encrypted),
                'addressLine2' => $this->decryptNullable($address->address_line_2_encrypted),
                'provinceId' => (string) $address->province_id,
                'amphoeId' => (string) $address->amphoe_id,
                'tambonId' => (string) $address->tambon_id,
                'postcode' => (string) $address->postcode,
                'version' => (int) $address->version,
            ],
        );
    }

    public function trainingFor(string $personId): array
    {
        if (! Str::isUuid($personId)) {
            return [];
        }

        return $this->database
            ->table('person_training_experiences')
            ->where('person_id', $personId)
            ->orderByDesc('started_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (object $row): array => $this->trainingView($row))
            ->all();
    }

    public function trainingForId(string $personId, string $trainingId): ?array
    {
        if (! Str::isUuid($personId) || ! Str::isUuid($trainingId)) {
            return null;
        }

        $row = $this->database
            ->table('person_training_experiences')
            ->where('id', $trainingId)
            ->where('person_id', $personId)
            ->first();

        return $row === null ? null : $this->trainingView($row);
    }

    public function updateProfile(ProfileUpdate $command): MutationResult
    {
        return $this->database->transaction(function () use ($command): MutationResult {
            $person = $this->database
                ->table('people')
                ->where('id', $command->personId)
                ->lockForUpdate()
                ->first();
            if ($person === null) {
                return MutationResult::denied();
            }

            if ((int) $person->profile_version !== $command->expectedVersion) {
                return MutationResult::stale();
            }

            $nextVersion = $command->expectedVersion + 1;
            $now = CarbonImmutable::now();
            $this->database->table('people')->where('id', $command->personId)->update([
                'given_name' => $command->givenName,
                'family_name' => $command->familyName,
                'profile_version' => $nextVersion,
                'updated_at' => $now,
            ]);

            $contact = $this->database
                ->table('person_contacts')
                ->where('person_id', $command->personId)
                ->lockForUpdate()
                ->first();
            $values = [
                'email_encrypted' => $this->encryptNullable($command->email),
                'phone_encrypted' => $this->encryptNullable($command->phone),
                'version' => $nextVersion,
                'updated_at' => $now,
            ];
            if ($contact === null) {
                $this->database->table('person_contacts')->insert(array_merge($values, [
                    'id' => (string) Str::uuid(),
                    'person_id' => $command->personId,
                    'created_at' => $now,
                ]));
            } else {
                $this->database
                    ->table('person_contacts')
                    ->where('person_id', $command->personId)
                    ->update($values);
            }

            $view = $this->profileFor($command->personId);

            return $view === null
                ? MutationResult::denied()
                : MutationResult::success($view->toArray());
        });
    }

    public function updateAddress(AddressUpdate $command): MutationResult
    {
        $referenceError = $this->validateAddressReferences($command);
        if ($referenceError !== null) {
            return MutationResult::invalidReference($referenceError);
        }

        return $this->database->transaction(function () use ($command): MutationResult {
            $person = $this->database
                ->table('people')
                ->where('id', $command->personId)
                ->lockForUpdate()
                ->first();
            if ($person === null) {
                return MutationResult::denied();
            }
            $address = $this->database
                ->table('person_addresses')
                ->where('person_id', $command->personId)
                ->lockForUpdate()
                ->first();
            $currentVersion = $address === null ? 0 : (int) $address->version;
            if ($currentVersion !== $command->expectedVersion) {
                return MutationResult::stale();
            }

            $tambon = $this->referenceItem(
                $this->references->children('amphoe', $command->amphoeId)->items,
                $command->tambonId,
            );
            if ($tambon === null || ! is_string($tambon['postcode'] ?? null)) {
                return MutationResult::invalidReference([
                    'errors' => ['tambon_id' => ['ตำบลไม่อยู่ในอำเภอที่เลือก']],
                ]);
            }

            $nextVersion = $currentVersion + 1;
            $now = CarbonImmutable::now();
            $values = [
                'address_line_1_encrypted' => $this->encrypter->encrypt($command->addressLine1),
                'address_line_2_encrypted' => $this->encryptNullable($command->addressLine2),
                'province_id' => $command->provinceId,
                'amphoe_id' => $command->amphoeId,
                'tambon_id' => $command->tambonId,
                'postcode' => $tambon['postcode'],
                'version' => $nextVersion,
                'updated_at' => $now,
            ];
            if ($address === null) {
                $this->database->table('person_addresses')->insert(array_merge($values, [
                    'id' => (string) Str::uuid(),
                    'person_id' => $command->personId,
                    'created_at' => $now,
                ]));
            } else {
                $this->database
                    ->table('person_addresses')
                    ->where('person_id', $command->personId)
                    ->update($values);
            }

            return MutationResult::success([
                'addressLine1' => $command->addressLine1,
                'addressLine2' => $command->addressLine2,
                'provinceId' => $command->provinceId,
                'amphoeId' => $command->amphoeId,
                'tambonId' => $command->tambonId,
                'postcode' => $tambon['postcode'],
                'version' => $nextVersion,
            ]);
        });
    }

    public function addTraining(TrainingUpdate $command): MutationResult
    {
        if (! $this->database->table('people')->where('id', $command->personId)->exists()) {
            return MutationResult::denied();
        }

        $id = (string) Str::uuid();
        $now = CarbonImmutable::now();
        $this->database->table('person_training_experiences')->insert([
            'id' => $id,
            'person_id' => $command->personId,
            'course_name_encrypted' => $this->encrypter->encrypt($command->courseName),
            'provider_name_encrypted' => $this->encrypter->encrypt($command->providerName),
            'started_on' => $command->startedOn->toDateString(),
            'ended_on' => $command->endedOn?->toDateString(),
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $row = $this->database->table('person_training_experiences')->where('id', $id)->first();

        return $row === null
            ? MutationResult::denied()
            : MutationResult::success($this->trainingView($row));
    }

    public function updateTraining(TrainingUpdate $command): MutationResult
    {
        if ($command->trainingId === null || $command->expectedVersion === null) {
            return MutationResult::denied();
        }

        return $this->database->transaction(function () use ($command): MutationResult {
            $row = $this->database
                ->table('person_training_experiences')
                ->where('id', $command->trainingId)
                ->where('person_id', $command->personId)
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                return MutationResult::denied();
            }
            if ((int) $row->version !== $command->expectedVersion) {
                return MutationResult::stale();
            }

            $this->database
                ->table('person_training_experiences')
                ->where('id', $command->trainingId)
                ->where('person_id', $command->personId)
                ->update([
                    'course_name_encrypted' => $this->encrypter->encrypt($command->courseName),
                    'provider_name_encrypted' => $this->encrypter->encrypt($command->providerName),
                    'started_on' => $command->startedOn->toDateString(),
                    'ended_on' => $command->endedOn?->toDateString(),
                    'version' => $command->expectedVersion + 1,
                    'updated_at' => CarbonImmutable::now(),
                ]);
            $updated = $this->database
                ->table('person_training_experiences')
                ->where('id', $command->trainingId)
                ->first();

            return $updated === null
                ? MutationResult::denied()
                : MutationResult::success($this->trainingView($updated));
        });
    }

    /** @return array<string, mixed>|null */
    private function validateAddressReferences(AddressUpdate $command): ?array
    {
        if ($this->referenceItem(
            $this->references->topLevel('province')->items,
            $command->provinceId,
        ) === null) {
            return ['errors' => ['province_id' => ['จังหวัดไม่พร้อมใช้งาน']]];
        }

        if ($this->referenceItem(
            $this->references->children('province', $command->provinceId)->items,
            $command->amphoeId,
        ) === null) {
            return ['errors' => ['amphoe_id' => ['อำเภอไม่อยู่ในจังหวัดที่เลือก']]];
        }

        if ($this->referenceItem(
            $this->references->children('amphoe', $command->amphoeId)->items,
            $command->tambonId,
        ) === null) {
            return ['errors' => ['tambon_id' => ['ตำบลไม่อยู่ในอำเภอที่เลือก']]];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function referenceItem(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function trainingView(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'courseName' => $this->encrypter->decrypt((string) $row->course_name_encrypted),
            'providerName' => $this->encrypter->decrypt((string) $row->provider_name_encrypted),
            'startedOn' => (string) $row->started_on,
            'endedOn' => $row->ended_on === null ? null : (string) $row->ended_on,
            'version' => (int) $row->version,
        ];
    }

    private function encryptNullable(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $this->encrypter->encrypt($value);
    }

    private function decryptNullable(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $this->encrypter->decrypt($value) : null;
    }
}
