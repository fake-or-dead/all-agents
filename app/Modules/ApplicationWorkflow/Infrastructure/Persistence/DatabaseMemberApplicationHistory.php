<?php

namespace App\Modules\ApplicationWorkflow\Infrastructure\Persistence;

use App\Modules\ApplicationWorkflow\Contracts\MemberApplicationHistory;
use App\Modules\ApplicationWorkflow\Data\MemberApplicationView;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseMemberApplicationHistory implements MemberApplicationHistory
{
    public function __construct(private ConnectionInterface $database) {}

    public function forPerson(string $personId): array
    {
        if (! Str::isUuid($personId)) {
            return [];
        }

        return $this->database
            ->table('application_workflow_facts')
            ->where('person_id', $personId)
            ->orderByDesc('id')
            ->get(['course_session_id', 'state', 'updated_at'])
            ->map(static fn (object $row): MemberApplicationView => new MemberApplicationView(
                (string) $row->course_session_id,
                (string) $row->state,
                $row->updated_at === null ? null : (string) $row->updated_at,
            ))
            ->all();
    }
}
