<?php

namespace App\Modules\CourseCatalog\Infrastructure\Persistence;

use App\Modules\CourseCatalog\Contracts\ApplicationFacts;
use Illuminate\Support\Facades\DB;

final class DatabaseApplicationFacts implements ApplicationFacts
{
    public function state(string $courseSessionId, ?string $actorId): string
    {
        if ($actorId === null) {
            return 'not-checked';
        }

        $state = DB::table('course_application_facts')
            ->where('course_session_id', $courseSessionId)
            ->where('actor_id', $actorId)
            ->value('state');

        return is_string($state) ? $state : 'none';
    }
}
