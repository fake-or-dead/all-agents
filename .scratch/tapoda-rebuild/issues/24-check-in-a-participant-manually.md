# Check in a participant manually

Status: `ready-for-agent`

## What to build

Let an authorized course-scoped operator find a confirmed participant using the approved minimum identity data, inspect readiness, verify identity manually, record arrival as an append-only event, and receive a clear operational receipt.

## Acceptance criteria

- [ ] Operator authentication and authorization are scoped to one approved course session with expiry, throttling, session rotation, and POST logout.
- [ ] Participant lookup accepts approved personal/passport identifiers and returns only the minimum fields authorized for check-in.
- [ ] Readiness explains not found, not confirmed, wrong session, already checked in, withdrawn, no-show, and other approved blockers without leaking unrelated records.
- [ ] Manual verification records operator, method, time, evidence classification, reason, and any approved mismatch override.
- [ ] Arrival appends check-in and identity-verification events and updates a derived attendance projection without replacing lifecycle history.
- [ ] Duplicate and concurrent scans are idempotent and return the existing receipt.
- [ ] The workstation UI supports scanner/keyboard use, Thai/English names, facility context, manual fallback, accessibility, and safe retry.
- [ ] `FLOW-CHECKIN-01`, course-scope attack, PII, CSRF, duplicate, concurrency, and audit tests pass.

## Blocked by

- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [19 Complete alumni/staff responses, decline, cancel, and withdrawal](./19-complete-alumni-staff-response-decline-cancel-and-withdrawal.md)
- [20 Operate one course session from Course Workspace](./20-operate-one-course-session-from-course-workspace.md)
