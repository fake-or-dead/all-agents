# Cut over one low-risk course cohort

Status: `ready-for-agent`

## What to build

Cut one approved low-risk course session from legacy to Tapoda Next using the rehearsed freeze, final delta, reconciliation, traffic switch, monitoring, rollback, forward-recovery, and support procedures.

## Acceptance criteria

- [ ] The selected cohort meets signed risk, data, compatibility, staff readiness, support, and go/no-go criteria.
- [ ] Shared accounts, people, reference data, documents, and cohort aggregates have one authoritative writer and one approved read path.
- [ ] A brief write freeze, final delta migration, counts, checksums, quarantine review, and output smoke tests complete successfully.
- [ ] Traffic switches only after health, worker, scheduler, database, cache, storage, notification, and compatibility readiness pass.
- [ ] Legacy cohort views become read-only and clearly identify the new authoritative workflow.
- [ ] Rollback by traffic switch is available only before target writes; tested forward recovery applies afterward.
- [ ] Monitoring covers errors, queue lag, delivery, exports, migration differences, action links, PII access, and operational support.
- [ ] Cutover events, decisions, incidents, reconciliation, and acceptance are recorded with owners and timestamps.

## Blocked by

- [08 Approve runtime, recovery, and cohort-cutover policy](./08-approve-runtime-recovery-and-cutover-policy.md)
- [30 Prove shadow parity and complete staff UAT](./30-prove-shadow-parity-and-complete-staff-uat.md)
