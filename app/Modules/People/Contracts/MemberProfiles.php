<?php

namespace App\Modules\People\Contracts;

use App\Modules\People\Data\AddressUpdate;
use App\Modules\People\Data\MemberProfileView;
use App\Modules\People\Data\MutationResult;
use App\Modules\People\Data\ProfileUpdate;
use App\Modules\People\Data\TrainingUpdate;

interface MemberProfiles
{
    public function profileFor(string $personId): ?MemberProfileView;

    /** @return list<array<string, mixed>> */
    public function trainingFor(string $personId): array;

    /** @return array<string, mixed>|null */
    public function trainingForId(string $personId, string $trainingId): ?array;

    public function updateProfile(ProfileUpdate $command): MutationResult;

    public function updateAddress(AddressUpdate $command): MutationResult;

    public function addTraining(TrainingUpdate $command): MutationResult;

    public function updateTraining(TrainingUpdate $command): MutationResult;
}
