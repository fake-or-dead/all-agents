<?php

namespace App\Modules\Audit\Contracts;

use App\Modules\Audit\Data\AuditEvent;

interface AuditLog
{
    public function append(AuditEvent $event): void;
}
