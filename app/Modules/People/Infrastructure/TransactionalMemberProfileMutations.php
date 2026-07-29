<?php

namespace App\Modules\People\Infrastructure;

use App\Modules\People\Contracts\MemberProfileMutations;
use App\Modules\People\Contracts\MemberProfiles;
use App\Modules\People\Contracts\ProfileActivityRecorder;
use App\Modules\People\Data\AddressUpdate;
use App\Modules\People\Data\MutationResult;
use App\Modules\People\Data\ProfileUpdate;
use App\Modules\People\Data\TrainingUpdate;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class TransactionalMemberProfileMutations implements MemberProfileMutations
{
    public function __construct(
        private ConnectionInterface $database,
        private MemberProfiles $profiles,
        private ProfileActivityRecorder $activity,
    ) {}

    public function updateProfile(string $accountId, ProfileUpdate $command): MutationResult
    {
        return $this->mutate(
            $accountId,
            $command->personId,
            'people.profile.updated',
            fn (): MutationResult => $this->profiles->updateProfile($command),
        );
    }

    public function updateAddress(string $accountId, AddressUpdate $command): MutationResult
    {
        return $this->mutate(
            $accountId,
            $command->personId,
            'people.address.updated',
            fn (): MutationResult => $this->profiles->updateAddress($command),
        );
    }

    public function addTraining(
        string $accountId,
        TrainingUpdate $command,
        string $idempotencyKey,
    ): MutationResult {
        $payloadDigest = hash('sha256', json_encode([
            'course_name' => $command->courseName,
            'provider_name' => $command->providerName,
            'started_on' => $command->startedOn->toDateString(),
            'ended_on' => $command->endedOn?->toDateString(),
        ], JSON_THROW_ON_ERROR));

        return $this->database->transaction(function () use (
            $accountId,
            $command,
            $idempotencyKey,
            $payloadDigest,
        ): MutationResult {
            $now = now();
            $claimId = (string) Str::uuid();
            $claimed = $this->database
                ->table('person_training_idempotency')
                ->insertOrIgnore([
                    'id' => $claimId,
                    'account_id' => $accountId,
                    'person_id' => $command->personId,
                    'idempotency_key' => $idempotencyKey,
                    'payload_digest' => $payloadDigest,
                    'training_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($claimed === 0) {
                $existing = $this->database
                    ->table('person_training_idempotency')
                    ->where('account_id', $accountId)
                    ->where('person_id', $command->personId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if (
                    $existing === null
                    || ! hash_equals((string) $existing->payload_digest, $payloadDigest)
                ) {
                    return MutationResult::idempotencyConflict();
                }

                $training = is_string($existing->training_id)
                    ? $this->profiles->trainingForId(
                        $command->personId,
                        $existing->training_id,
                    )
                    : null;

                return $training === null
                    ? MutationResult::denied()
                    : MutationResult::replay($training);
            }

            $result = $this->profiles->addTraining($command);
            $this->activity->record(
                $accountId,
                $command->personId,
                'people.training.added',
                $result->successful ? 'succeeded' : 'denied',
                (string) Str::uuid(),
                ['reason' => $result->code],
            );
            if ($result->successful) {
                $this->database
                    ->table('person_training_idempotency')
                    ->where('id', $claimId)
                    ->update([
                        'training_id' => $result->value['id'],
                        'updated_at' => now(),
                    ]);
            }

            return $result;
        });
    }

    public function updateTraining(string $accountId, TrainingUpdate $command): MutationResult
    {
        return $this->mutate(
            $accountId,
            $command->personId,
            'people.training.updated',
            fn (): MutationResult => $this->profiles->updateTraining($command),
        );
    }

    /** @param Closure(): MutationResult $mutation */
    private function mutate(
        string $accountId,
        string $personId,
        string $action,
        Closure $mutation,
    ): MutationResult {
        return $this->database->transaction(function () use (
            $accountId,
            $personId,
            $action,
            $mutation,
        ): MutationResult {
            $result = $mutation();
            $this->activity->record(
                $accountId,
                $personId,
                $action,
                $result->successful ? 'succeeded' : 'denied',
                (string) Str::uuid(),
                ['reason' => $result->code],
            );

            return $result;
        });
    }
}
