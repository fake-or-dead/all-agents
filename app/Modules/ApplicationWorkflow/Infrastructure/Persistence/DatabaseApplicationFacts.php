<?php

namespace App\Modules\ApplicationWorkflow\Infrastructure\Persistence;

use App\Modules\ApplicationWorkflow\Contracts\ApplicationFacts;
use Illuminate\Support\Facades\DB;

final class DatabaseApplicationFacts implements ApplicationFacts
{
    public function state(string $courseSessionId, ?string $personId): string
    {
        if ($personId === null) {
            return 'not-checked';
        }

        $state = DB::table('application_workflow_facts')
            ->where('course_session_id', $courseSessionId)
            ->where('person_id', $personId)
            ->value('state');

        return is_string($state) ? $state : 'none';
    }
}
