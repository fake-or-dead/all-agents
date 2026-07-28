# Bootstrap one deployable audited platform path

Status: `ready-for-agent`

## What to build

Create the first production-shaped Tapoda Next path: one Laravel deployable that serves a Thai-first system-state page and safe health endpoints, records an audit event, runs queued work through a deterministic fake, and can be built, tested, deployed, observed, and rolled back as one immutable artifact.

## Acceptance criteria

- [ ] The approved Laravel, PHP, Inertia React, TypeScript, Tailwind, PostgreSQL, and Redis/Horizon stack runs reproducibly with locked dependencies.
- [ ] Liveness, readiness, database, queue, scheduler, build version, and migration status are exposed without leaking secrets or personal data.
- [ ] One request crosses HTTP, authorization, transaction, audit, outbox, worker, and observable completion boundaries.
- [ ] Design tokens generate web, TypeScript, email, and print adapters; the first system-state UI passes Thai, keyboard, contrast, 320px, and 200% zoom checks.
- [ ] CI enforces backend, frontend, architecture, token drift, security, accessibility, visual, and bundle checks.
- [ ] Deployment creates an immutable artifact with migrations, smoke checks, worker/scheduler health, monitoring, rollback, and backup-restore instructions.
- [ ] Production and deterministic fake adapters are configured without embedding provider behavior in business modules.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [04 Approve compatibility and coexistence contracts](./04-approve-compatibility-and-coexistence-contracts.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
- [08 Approve runtime, recovery, and cohort-cutover policy](./08-approve-runtime-recovery-and-cutover-policy.md)
