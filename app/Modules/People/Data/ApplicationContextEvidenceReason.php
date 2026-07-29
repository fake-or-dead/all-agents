<?php

namespace App\Modules\People\Data;

enum ApplicationContextEvidenceReason: string
{
    case NoEvidence = 'no-evidence';
    case EvidenceExpired = 'evidence-expired';
    case UnreadableEvidence = 'unreadable-evidence';
    case InvalidEvidence = 'invalid-evidence';
}
