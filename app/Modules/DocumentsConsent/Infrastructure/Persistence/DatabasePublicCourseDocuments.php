<?php

namespace App\Modules\DocumentsConsent\Infrastructure\Persistence;

use App\Modules\DocumentsConsent\Contracts\PublicCourseDocuments;
use App\Modules\DocumentsConsent\Data\PublicDocument;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DatabasePublicCourseDocuments implements PublicCourseDocuments
{
    public function forSession(string $courseSessionId): array
    {
        return $this->publicProjection()
            ->where('course_session_id', $courseSessionId)
            ->orderBy('title_th')
            ->get()
            ->map(fn (object $row): PublicDocument => $this->document($row))
            ->all();
    }

    public function findByKey(string $key): ?PublicDocument
    {
        if (preg_match('/^[a-z0-9-]{1,64}$/', $key) !== 1) {
            return null;
        }

        $row = $this->publicProjection()->where('key', $key)->first();

        return $row === null ? null : $this->document($row);
    }

    private function publicProjection(): Builder
    {
        return DB::table('document_publication_projections')
            ->where('visibility', 'public')
            ->where('approval_state', 'approved')
            ->where('lifecycle_state', 'active')
            ->whereNull('quarantine_reason')
            ->whereNotNull('checksum')
            ->where('checksum', '<>', '');
    }

    private function document(object $row): PublicDocument
    {
        return new PublicDocument(
            (string) $row->key,
            (string) $row->title_th,
            (int) $row->version,
            (string) $row->checksum,
            (string) $row->disposition,
        );
    }
}
