<?php

namespace App\Modules\People\Data;

final readonly class ApplicationContextEvidenceResolution
{
    private function __construct(
        public ApplicationContextEvidenceStatus $status,
        public ?ApplicationContextEvidenceReason $reason,
        public ?ApplicationContextEvidence $evidence,
    ) {}

    public static function missing(): self
    {
        return new self(
            ApplicationContextEvidenceStatus::Missing,
            ApplicationContextEvidenceReason::NoEvidence,
            null,
        );
    }

    public static function resolved(ApplicationContextEvidence $evidence): self
    {
        return new self(ApplicationContextEvidenceStatus::Resolved, null, $evidence);
    }

    public static function expired(): self
    {
        return new self(
            ApplicationContextEvidenceStatus::Stale,
            ApplicationContextEvidenceReason::EvidenceExpired,
            null,
        );
    }

    public static function unreadable(): self
    {
        return new self(
            ApplicationContextEvidenceStatus::Stale,
            ApplicationContextEvidenceReason::UnreadableEvidence,
            null,
        );
    }

    public static function invalid(): self
    {
        return new self(
            ApplicationContextEvidenceStatus::Stale,
            ApplicationContextEvidenceReason::InvalidEvidence,
            null,
        );
    }
}
