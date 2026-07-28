# Map target schema and migration rules

Status: `ready-for-human`

## What to build

Reconcile the production schema with the target modular model. Give every retained source field one target owner, transformation, provenance rule, constraint, validation rule, or approved retirement outcome. Define idempotent migration and quarantine behavior.

## Acceptance criteria

- [ ] Every live source table and column maps to a target record, compatibility projection, retained raw value, or approved retirement.
- [x] Canonical identifiers, legacy identifiers, migration batches, source provenance, and confidence are defined.
- [x] Target constraints cover ownership, uniqueness, immutable records, one-use tokens, optimistic locking, and append-only events.
- [x] Invalid or contradictory rows enter quarantine with a reason and owner; nothing is silently dropped or invented.
- [x] ETL order, restartability, checksums, rollback boundaries, and reconciliation queries are specified.
- [x] Shared aggregates have one authoritative writer and explicit read paths in every migration phase.
- [ ] Architecture and data owners approve Gate G1.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)

## Comments

### Local completion record — 2026-07-29

- Source SHA: `3d2c3a4b843f73cf0b19c5cb9a4d2e54e80d78aa` (`uat-20260526`), static repository inspection only.
- Local mapping and verification contract: [`target-schema-mapping.md`](../../../docs/decisions/local/target-schema-mapping.md); coexistence contract: [`compatibility-contracts.md`](../../../docs/decisions/local/compatibility-contracts.md).
- Verification: table-to-owner mapping, fixed-batch ETL replay, source/target checksum, and quarantine simulations are deterministic and contain no production records.
- Exclusions: “every live source table and column” remains unchecked; live DDL/data profiling, G2 lifecycle decisions, and architecture/data-owner approval are production-only.
