# Run bulk transitions, reminders, and cross-course work queues

Status: `ready-for-agent`

## What to build

Let authorized staff find aging or failed work across course sessions, preview a bulk lifecycle or communication action, execute it idempotently, and inspect per-record results. Replace direct status replacement and disabled one-record reminder behavior with owning-module commands and durable workers.

## Acceptance criteria

- [ ] Work queues cover submitted-unassigned, overdue review, unsent/failed invitation, overdue confirmation, check-in exception, failed export, migration quarantine, expiring document/consent, and integration failure.
- [ ] Bulk invite, request confirmation, cancel, no-show, check-in, complete, and staff-conversion actions use the approved lifecycle and authorization rules.
- [ ] Every bulk action requires explicit selection, affected/blocked preview, material reason, expected versions, confirmation, and idempotency key.
- [ ] Execution returns a durable per-record success, denied, stale, failed, or retryable result without rolling unrelated records into a false success.
- [ ] Reminder policy enforces due time, cooldown, deduplication, provider retry, bounce handling, and observable delivery state.
- [ ] Worker and scheduler health, duplicate runs, partial failures, restart, dead-letter, audit, and operator retry are tested.
- [ ] `FLOW-COURSE-01`, `FLOW-INV-03`, and `FLOW-MAINT-01` parity scenarios pass.

## Blocked by

- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [17 Review one immutable submission and record a decision](./17-review-one-immutable-submission-and-record-a-decision.md)
- [18 Invite and confirm one selected applicant](./18-invite-and-confirm-one-selected-applicant.md)
- [19 Complete alumni/staff responses, decline, cancel, and withdrawal](./19-complete-alumni-staff-response-decline-cancel-and-withdrawal.md)
- [20 Operate one course session from Course Workspace](./20-operate-one-course-session-from-course-workspace.md)
