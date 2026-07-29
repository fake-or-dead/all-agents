<?php

namespace App\Modules\ApplicationWorkflow\Data;

final readonly class MemberApplicationView
{
    public function __construct(
        public string $courseSessionId,
        public string $state,
        public ?string $lastSavedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'courseSessionId' => $this->courseSessionId,
            'state' => $this->state,
            'nextTask' => null,
            'nextTaskUnavailableReason' => 'ยังไม่มีข้อมูลขั้นตอนถัดไปจากระบบใบสมัคร',
            'deadline' => null,
            'deadlineUnavailableReason' => 'ยังไม่มีกำหนดส่งจากระบบใบสมัคร',
            'lastSavedAt' => $this->lastSavedAt,
            'lastSavedAtUnavailableReason' => $this->lastSavedAt === null
                ? 'รายการเดิมไม่มีเวลาบันทึกที่ตรวจสอบได้'
                : null,
            'resumeUrl' => null,
            'resumeUnavailableReason' => 'ยังไม่มีเส้นทางทำรายการต่อที่ได้รับอนุญาต',
            'history' => [[
                'state' => $this->state,
                'occurredAt' => $this->lastSavedAt,
                'occurredAtUnavailableReason' => $this->lastSavedAt === null
                    ? 'ไม่มีเวลาของเหตุการณ์ในข้อมูลเดิม'
                    : null,
            ]],
        ];
    }
}
