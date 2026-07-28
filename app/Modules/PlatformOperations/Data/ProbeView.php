<?php

namespace App\Modules\PlatformOperations\Data;

final readonly class ProbeView
{
    public function __construct(
        public string $id,
        public string $status,
        public string $correlationId,
        public ?string $completionCode,
        public int $auditEventCount,
    ) {}

    /**
     * @return array{
     *   id: string,
     *   status: string,
     *   correlation_id: string,
     *   completion: array{code: string}|null,
     *   audit_event_count: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'correlation_id' => $this->correlationId,
            'completion' => $this->completionCode === null ? null : ['code' => $this->completionCode],
            'audit_event_count' => $this->auditEventCount,
        ];
    }
}
