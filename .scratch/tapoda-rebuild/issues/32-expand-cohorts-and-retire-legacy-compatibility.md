# Expand cohorts and retire legacy compatibility

Status: `ready-for-agent`

## What to build

Expand Tapoda Next cohort by cohort using the accepted cutover controls. Retire compatibility adapters, legacy assets, credentials, tokens, routes, tables, CSS, and personal data only when telemetry, retention, reconciliation, ownership, and rollback evidence meet the approved thresholds.

## Acceptance criteria

- [ ] Each new cohort repeats readiness, migration, shadow, go/no-go, cutover, monitoring, and acceptance controls.
- [ ] Legacy route, token, password-hash, table-read, document URL, notification observation, report oracle, card-reader, and CSS adapters expose removal metrics.
- [ ] An adapter is removed only after its owner-approved traffic, expiry, account-transition, reconciliation, retention, and rollback criteria pass.
- [ ] Every `W001-W099`, `A001-A010`, Blade file, root artifact, PDF, command, email, report, and device interaction has a final disposition.
- [ ] Required audits and historical records remain accessible for their retention period before source retirement.
- [ ] Legacy PII is destroyed only through the signed retention and deletion policy with verifiable completion evidence.
- [ ] Bootstrap/AdminLTE/jQuery, page-local CSS/JS, fixed question IDs, unsafe message rendering, legacy token decoding, and obsolete password verification are absent after their final approved milestones.
- [ ] Final security, privacy, accessibility, performance, restore, reconciliation, and coverage gates pass with signed product and operations acceptance.

## Blocked by

- [04 Approve compatibility and coexistence contracts](./04-approve-compatibility-and-coexistence-contracts.md)
- [31 Cut over one low-risk course cohort](./31-cut-over-one-low-risk-course-cohort.md)
