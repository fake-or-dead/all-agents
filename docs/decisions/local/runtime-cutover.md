# Local runtime and cohort-cutover decision

**Decision ID:** `LOCAL-G8-G9-RUNTIME`
**Applies to:** `G8` infrastructure and `G9` cutover
**Status:** Local operating contract accepted; production topology and go/no-go excluded

## Local runtime contract

The target is a Laravel modular monolith: HTTP application, queue worker, scheduler, PostgreSQL, Redis/Valkey (non-cluster for Horizon), versioned object-store adapter, and provider adapters. Local implementations use isolated PostgreSQL, local Redis/Valkey, local/in-memory object storage, fixed clock, deterministic mail/card fakes, and PII-redacted structured logs.

Required controls: immutable build artifact; `/health` liveness and `/ready` dependency readiness; migration dry-run; queue lag/failed-job signal; scheduler heartbeat; outbox retry/dead-letter signal; migration reconciliation dashboard; deployment/correlation ID; no secrets in repository, fixture, output, or logs.

`CLARIFY-006` is unresolved: SSL provider, access, renewal, and ownership are production-only decisions. Local TLS simulation cannot close it.

## Recovery policy

| Failure | Local action | Deterministic success condition |
|---|---|---|
| Failed deployment | Roll back immutable application image/config only. | Previous image passes health and smoke fixtures. |
| Failed expand migration | Stop rollout; retain backward-compatible schema; replay dry-run. | No destructive schema reversal required. |
| Failure after target write | Forward recover with idempotent command/ETL and append-only audit. | Reconciliation converges; no legacy write resumes for switched cohort. |
| Worker/provider failure | Retry through outbox with fixed backoff; dead-letter after configured attempts. | One notification request, ordered attempts, truthful delivery state. |
| Restore exercise | Restore synthetic backup into isolated runtime; run checksums and smoke. | RPO/RTO simulation values recorded as simulated, not approved. |

## One-course-session cutover runbook

This runbook applies to `FLOW-APP-01..06`, `FLOW-REVIEW-01`, `FLOW-INV-01..03`, `FLOW-COURSE-01`, `FLOW-CHECKIN-01`, `FLOW-OPS-01`, `FLOW-REPORT-01..02`, `FLOW-NOTIFY-01`, and `FLOW-MAINT-01`.

1. Select one low-risk **Course session** only after `G0-G7` pass or have approved exceptions. Confirm one writer for Account, Person, Reference Data, Documents, Application, Review, Invitation, and report artifacts.
2. Freeze new legacy writes for the selected Course session. Keep other cohorts untouched.
3. Run final delta ETL with fixed batch ID; compare counts/checksums by Course session, Center, Person, category, state, teacher, check-in, document, and report group. Quarantine blocks the switch when critical.
4. Reconcile action tokens and password bridge eligibility. Do not copy plaintext secrets or tokens.
5. Run target smoke: health, readiness, sign-in, catalog, application draft/submission, review decision, invitation response, check-in manual fallback, report request, and failed notification retry.
6. Switch cohort routes. Legacy selected-cohort screens become read-only; compatibility adapter serves safe URLs only.
7. Monitor audit/outbox/queue/error/reconciliation signals. Support uses correlation IDs, not PII.
8. Before any target write, rollback is traffic switch back. After any target write, use forward recovery only; do not resume legacy writes for that Course session.
9. Record go/no-go decision, owner, timestamps, fixture/checksum evidence, incidents, and post-cutover acceptance. Expand only after the retention/monitoring window passes.

## Local cutover simulation

Fixed fixture `cohort-local-001`, fixed clock `2026-07-29T00:00:00+07:00`, and batch `local-20260729-cutover-a` must execute this sequence twice:

| Scenario | Required result |
|---|---|
| clean rehearsal | final delta checksum equals target checksum; route switch succeeds; legacy projection rejects writes; all fixture flows pass. |
| failure before target write | traffic switch returns to legacy; target has no domain writes. |
| failure after target write | forward recovery resumes idempotently; one writer remains target; audit/reconciliation converge. |
| queue outage | business transaction stores outbox request; no false delivery success; retry reaches deterministic fake receipt. |
| duplicate token/check-in/bulk request | one effective domain event/result; replays are safe and auditable. |
| restore | isolated backup restores and passes health, checksum, and smoke suite. |

## Production-only exclusions and signoffs

Not verified locally: AWS Thailand regional availability/fallback; residency; encryption/key management; backup region; RPO/RTO; capacity/availability/cost; managed PostgreSQL/Redis/storage/CDN/WAF/secrets/logging topology; DNS/TLS and `CLARIFY-006`; real provider behavior; production backups; operational staffing/support; traffic volumes; and Platform/Delivery owner signatures.

This document does not approve production `G8`, `G9`, a cohort switch, or legacy retirement. It is the deterministic contract those approvals must exercise.
