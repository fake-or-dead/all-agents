# Approve scoped RBAC, sensitive fields, and privacy

Status: `ready-for-human`

## What to build

Define least-privilege access for every actor, action, center, course session, record, and sensitive field. Bind this access model to privacy, retention, support access, audit, export, and account-administration rules.

## Acceptance criteria

- [x] Locally satisfied: actor/action/resource matrix covers visitors, applicants, alumni, staff applicants, course staff, reviewers, managers, teachers, check-in operators, operations, reporting, support, administrators, and workers.
- [x] Locally satisfied: center and course-session scope is explicit for every staff operation.
- [x] Locally satisfied: health, mental-health, substance-use, medication, national ID, emergency contact, report, print, and export fields have separate permission decisions.
- [x] Locally satisfied: support access requires a case reference, reason, expiry, and auditable grant; privileged self-approval is prohibited.
- [x] Locally satisfied: self-disable, last-administrator, account recovery, role grant, and sensitive export safeguards are defined.
- [ ] Production signoff required: legal basis, consent, retention, correction, deletion, breach handling, and PII audit requirements.
- [ ] Production signoff required: Product and Privacy owners approve Gates G4 and G7.

## Local completion record

2026-07-29 — [Local RBAC and privacy contract](../../../docs/decisions/local/rbac-privacy.md) defines executable local defaults. Production legal/privacy approval is explicitly excluded.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
