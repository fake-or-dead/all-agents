<?php

namespace App\Modules\DocumentsConsent\Infrastructure;

use App\Modules\DocumentsConsent\Contracts\ConsentAcceptanceService;
use App\Modules\DocumentsConsent\Data\ConsentDocumentVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DatabaseConsentAcceptanceService implements ConsentAcceptanceService
{
    public function __construct(private ConnectionInterface $database) {}

    public function currentRegistrationConsent(): ConsentDocumentVersion
    {
        $version = $this->registrationVersionQuery()
            ->where('consent_document_versions.status', 'published')
            ->latest('consent_document_versions.published_at')
            ->first();

        if ($version === null) {
            throw new RuntimeException('No published registration consent is available.');
        }

        return $this->map($version);
    }

    public function publishedVersion(string $versionId): ?ConsentDocumentVersion
    {
        $version = $this->registrationVersionQuery()
            ->where('consent_document_versions.id', $versionId)
            ->where('consent_document_versions.status', 'published')
            ->first();

        return $version === null ? null : $this->map($version);
    }

    public function acceptRegistration(
        string $personId,
        string $requestedVersionId,
        array $evidence,
        CarbonImmutable $acceptedAt,
    ): string {
        $current = $this->currentRegistrationConsent();

        if (
            ! hash_equals($current->id, $requestedVersionId)
            || ! hash_equals($current->checksum, hash('sha256', $current->content))
        ) {
            throw new RuntimeException('Registration consent is stale or invalid.');
        }

        $acceptanceId = (string) Str::uuid();
        $this->database->table('consent_acceptances')->insert([
            'id' => $acceptanceId,
            'person_id' => $personId,
            'document_version_id' => $current->id,
            'document_checksum' => $current->checksum,
            'locale' => $current->locale,
            'context' => 'registration',
            'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'accepted_at' => $acceptedAt,
        ]);

        return $acceptanceId;
    }

    private function registrationVersionQuery(): Builder
    {
        return $this->database
            ->table('consent_document_versions')
            ->join(
                'consent_documents',
                'consent_documents.id',
                '=',
                'consent_document_versions.document_id',
            )
            ->where('consent_documents.document_key', 'registration-consent')
            ->select([
                'consent_document_versions.id',
                'consent_documents.title',
                'consent_document_versions.version_label',
                'consent_document_versions.locale',
                'consent_document_versions.content',
                'consent_document_versions.content_checksum',
            ]);
    }

    private function map(object $version): ConsentDocumentVersion
    {
        return new ConsentDocumentVersion(
            id: $version->id,
            title: $version->title,
            versionLabel: $version->version_label,
            locale: $version->locale,
            content: $version->content,
            checksum: $version->content_checksum,
        );
    }
}
