# Approve runtime, recovery, and cohort-cutover policy

Status: `ready-for-human`

## What to build

Approve the production topology and operating model, including service availability, data residency, cost, secrets, monitoring, backup, recovery, deployment, support, cohort cutover, rollback, and forward recovery.

## Acceptance criteria

- [ ] Regional availability and fallback are verified for web, worker, scheduler, PostgreSQL, Redis/Valkey, storage, CDN/WAF, secrets, logs, and monitoring.
- [ ] Residency, encryption, backup region, RPO, RTO, capacity, availability, and cost are approved.
- [ ] Immutable deployment, health, smoke, migration, rollback, queue, scheduler, and failed-job controls are specified.
- [ ] Backup restoration, failed deployment, failed migration, and forward-recovery drills have owners and success criteria.
- [ ] One-cohort cutover defines freeze, final delta, checksums, traffic switch, read-only legacy behavior, support, and go/no-go authority.
- [ ] `CLARIFY-006` resolves SSL provider, access, renewal, and ownership.
- [ ] Platform and delivery owners approve Gates G8 and G9.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [04 Approve compatibility and coexistence contracts](./04-approve-compatibility-and-coexistence-contracts.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
