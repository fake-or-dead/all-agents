<?php

namespace App\Modules\People\Contracts;

use App\Modules\People\Data\AddressUpdate;
use App\Modules\People\Data\MutationResult;
use App\Modules\People\Data\ProfileUpdate;
use App\Modules\People\Data\TrainingUpdate;

interface MemberProfileMutations
{
    public function updateProfile(string $accountId, ProfileUpdate $command): MutationResult;

    public function updateAddress(string $accountId, AddressUpdate $command): MutationResult;

    public function addTraining(
        string $accountId,
        TrainingUpdate $command,
        string $idempotencyKey,
    ): MutationResult;

    public function updateTraining(string $accountId, TrainingUpdate $command): MutationResult;
}
