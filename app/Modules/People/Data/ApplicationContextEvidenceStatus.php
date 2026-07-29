<?php

namespace App\Modules\People\Data;

enum ApplicationContextEvidenceStatus: string
{
    case Resolved = 'resolved';
    case Missing = 'missing';
    case Stale = 'stale';
}
