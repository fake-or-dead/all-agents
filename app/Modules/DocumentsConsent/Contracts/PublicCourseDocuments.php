<?php

namespace App\Modules\DocumentsConsent\Contracts;

use App\Modules\DocumentsConsent\Data\PublicDocument;

interface PublicCourseDocuments
{
    /**
     * @return list<PublicDocument>
     */
    public function forSession(string $courseSessionId): array;

    public function findByKey(string $key): ?PublicDocument;
}
