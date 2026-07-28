# Capture production truth baseline

Status: `ready-for-human`

## What to build

Produce the reproducible production baseline required by Gate G0. Capture the deployed schema, data shape, runtime behavior, operational jobs, integrations, artifacts, and active compatibility surfaces without exposing personal data. Record contradictions against repository evidence instead of silently resolving them.

## Acceptance criteria

- [ ] Live tables, columns, indexes, constraints, collations, row counts, volumes, nulls, duplicates, orphans, invalid dates, and status distributions are captured reproducibly.
- [ ] Rejection actors, invitation responses, alumni evidence, staff intent and assignment, training data, consent, questions, course codes, and teacher codes are profiled.
- [ ] Deployed routes, redirects, bookmarks, active action links, PDFs, attachments, emails, reports, scheduled commands, manual workarounds, and check-in workstations are inventoried.
- [ ] Evidence is sanitized, access-controlled, timestamped, and traceable to its source.
- [ ] Repository-versus-production contradictions and urgent security exposures have named owners and explicit follow-up actions.
- [ ] A data owner accepts the baseline as sufficient for downstream schema and lifecycle decisions.

## Blocked by

None - can start immediately

## Comments

### Local completion record — 2026-07-29

- Source SHA: `3d2c3a4b843f73cf0b19c5cb9a4d2e54e80d78aa` (`uat-20260526`), static repository inspection only.
- Local evidence and deterministic fixture boundary: [`production-baseline.md`](../../../docs/decisions/local/production-baseline.md).
- Verification: source route/table/flow evidence reconciles to `docs/rebuild/coverage-matrix.md`, `current-flow-ledger.md`, and `module-blueprint.md`; fixture simulations are specified without production records.
- Exclusions: all live DDL/data/runtime/operations evidence, access control, timestamped production capture, contradictions from deployment, and data-owner acceptance. No production acceptance criterion is checked by this local record.
