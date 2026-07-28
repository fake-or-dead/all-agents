# Approve scoped RBAC, sensitive fields, and privacy

Status: `ready-for-human`

## What to build

Define least-privilege access for every actor, action, center, course session, record, and sensitive field. Bind this access model to privacy, retention, support access, audit, export, and account-administration rules.

## Acceptance criteria

- [ ] The actor/action/resource matrix covers visitors, applicants, alumni, staff applicants, course staff, reviewers, managers, teachers, check-in operators, operations, reporting, support, administrators, and workers.
- [ ] Center and course-session scope is explicit for every staff operation.
- [ ] Health, mental-health, substance-use, medication, national ID, emergency contact, report, print, and export fields have separate permission decisions.
- [ ] Support access requires a case reference, reason, expiry, and auditable grant; privileged self-approval is prohibited.
- [ ] Self-disable, last-administrator, account recovery, role grant, and sensitive export safeguards are approved.
- [ ] Legal basis, consent, retention, correction, deletion, breach handling, and PII audit requirements are signed.
- [ ] Product and privacy owners approve Gates G4 and G7.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
