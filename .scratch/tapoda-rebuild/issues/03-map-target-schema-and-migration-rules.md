# Map target schema and migration rules

Status: `ready-for-human`

## What to build

Reconcile the production schema with the target modular model. Give every retained source field one target owner, transformation, provenance rule, constraint, validation rule, or approved retirement outcome. Define idempotent migration and quarantine behavior.

## Acceptance criteria

- [ ] Every live source table and column maps to a target record, compatibility projection, retained raw value, or approved retirement.
- [ ] Canonical identifiers, legacy identifiers, migration batches, source provenance, and confidence are defined.
- [ ] Target constraints cover ownership, uniqueness, immutable records, one-use tokens, optimistic locking, and append-only events.
- [ ] Invalid or contradictory rows enter quarantine with a reason and owner; nothing is silently dropped or invented.
- [ ] ETL order, restartability, checksums, rollback boundaries, and reconciliation queries are specified.
- [ ] Shared aggregates have one authoritative writer and explicit read paths in every migration phase.
- [ ] Architecture and data owners approve Gate G1.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
