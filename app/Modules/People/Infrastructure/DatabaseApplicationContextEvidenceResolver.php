<?php

namespace App\Modules\People\Infrastructure;

use App\Modules\People\Contracts\ApplicationContextEvidenceResolver;
use App\Modules\People\Data\ApplicationContextEvidence;
use App\Modules\People\Data\ApplicationContextEvidenceResolution;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

final readonly class DatabaseApplicationContextEvidenceResolver implements ApplicationContextEvidenceResolver
{
    public function __construct(
        private ConnectionInterface $database,
        private ApplicationContextEvidenceCipher $cipher,
    ) {}

    public function resolveForPerson(
        string $personId,
        CarbonImmutable $at,
    ): ApplicationContextEvidenceResolution {
        if (! Str::isUuid($personId)) {
            return ApplicationContextEvidenceResolution::missing();
        }

        $row = $this->database
            ->table('person_application_context_evidence')
            ->where('person_id', $personId)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('version')
            ->orderByDesc('effective_at')
            ->first();

        if ($row === null) {
            return ApplicationContextEvidenceResolution::missing();
        }

        $staleAt = $row->stale_at === null
            ? null
            : CarbonImmutable::parse((string) $row->stale_at);
        if ($staleAt !== null && $at->greaterThanOrEqualTo($staleAt)) {
            return ApplicationContextEvidenceResolution::expired();
        }

        try {
            $facts = $this->cipher->decrypt(
                (string) $row->encryption_key_version,
                (string) $row->facts_encrypted,
            );
        } catch (Throwable) {
            return ApplicationContextEvidenceResolution::unreadable();
        }

        $effectiveAt = CarbonImmutable::parse((string) $row->effective_at);
        $birthDate = $this->birthDate($facts['birthDate'] ?? null);
        if (
            $birthDate === null
            || $birthDate->isAfter($at)
            || ! $this->factsAreCanonical($facts, $row, $effectiveAt, $staleAt)
        ) {
            return ApplicationContextEvidenceResolution::invalid();
        }

        return ApplicationContextEvidenceResolution::resolved(
            new ApplicationContextEvidence(
                personId: (string) $row->person_id,
                version: (int) $row->version,
                birthDate: $birthDate,
                approvedCategory: (string) $facts['approvedCategory'],
                layMonasticCategory: (string) $facts['layMonasticCategory'],
                provenance: (string) $facts['provenance'],
                effectiveAt: $effectiveAt,
                staleAt: $staleAt,
            ),
        );
    }

    private function birthDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $birthDate = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (Throwable) {
            return null;
        }

        return $birthDate->toDateString() === $value
            ? $birthDate
            : null;
    }

    /** @param array<string, mixed> $facts */
    private function factsAreCanonical(
        array $facts,
        object $row,
        CarbonImmutable $effectiveAt,
        ?CarbonImmutable $staleAt,
    ): bool {
        $approvedCategory = $facts['approvedCategory'] ?? null;
        $layMonasticCategory = $facts['layMonasticCategory'] ?? null;
        $provenance = $facts['provenance'] ?? null;

        return is_string($approvedCategory)
            && in_array($approvedCategory, ['female', 'male', 'monastic'], true)
            && is_string($layMonasticCategory)
            && in_array($layMonasticCategory, ['lay', 'monastic'], true)
            && (
                ($layMonasticCategory === 'monastic' && $approvedCategory === 'monastic')
                || ($layMonasticCategory === 'lay' && in_array(
                    $approvedCategory,
                    ['female', 'male'],
                    true,
                ))
            )
            && is_string($provenance)
            && $provenance !== ''
            && mb_strlen($provenance) <= 96
            && $facts === [
                'personId' => (string) $row->person_id,
                'version' => (int) $row->version,
                'birthDate' => $facts['birthDate'] ?? null,
                'approvedCategory' => $approvedCategory,
                'layMonasticCategory' => $layMonasticCategory,
                'provenance' => $provenance,
                'effectiveAt' => $effectiveAt->utc()->toIso8601String(),
                'staleAt' => $staleAt?->utc()->toIso8601String(),
            ];
    }
}
