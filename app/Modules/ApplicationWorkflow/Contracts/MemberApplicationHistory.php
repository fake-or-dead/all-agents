<?php

namespace App\Modules\ApplicationWorkflow\Contracts;

use App\Modules\ApplicationWorkflow\Data\MemberApplicationView;

interface MemberApplicationHistory
{
    /** @return list<MemberApplicationView> */
    public function forPerson(string $personId): array;
}
