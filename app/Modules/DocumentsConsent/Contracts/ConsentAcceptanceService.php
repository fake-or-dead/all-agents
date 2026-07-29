<?php

namespace App\Modules\DocumentsConsent\Contracts;

use App\Modules\DocumentsConsent\Data\ConsentDocumentVersion;
use Carbon\CarbonImmutable;

interface ConsentAcceptanceService
{
    public function currentRegistrationConsent(): ConsentDocumentVersion;

    public function publishedVersion(string $versionId): ?ConsentDocumentVersion;

    /** @param array<string, scalar|null> $evidence */
    public function acceptRegistration(
        string $personId,
        string $requestedVersionId,
        array $evidence,
        CarbonImmutable $acceptedAt,
    ): string;
}
