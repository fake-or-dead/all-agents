# Local compatibility and coexistence contracts

**Decision ID:** `LOCAL-G3-COMPATIBILITY`
**Applies to:** `G3`; supports `G1`, `G8`, `G9`
**Status:** Local adapter contract accepted; production telemetry, owner approvals, and retirement signoffs excluded

## Non-negotiable safety boundary

No adapter preserves state-changing GET, arbitrary message HTML, public PII lookup, direct object reference, plaintext credential, public diagnostic, or insecure token replay. These legacy behaviors resolve to a safe typed outcome, authenticated diagnostic, or removal response; they never invoke a domain command.

Every temporary adapter emits a PII-safe event with `adapter_id`, route/action class, outcome code, correlation ID, cohort, and sunset version. It has a named module owner, deadline `before G9 go/no-go`, removal threshold `zero production uses for approved retention window`, and rollback path `restore the adapter-only route mapping; never roll back domain writes`.

## Endpoint disposition ledger

| IDs | Flow IDs | Local disposition and owner |
|---|---|---|
| `W001`, `W004-W005`, `W085`, `A001`, `A009` | `FLOW-DEBUG-01` | Remove from production. Local deterministic diagnostics only, owned by Platform Operations. |
| `W002-W003`, `W006-W008`, `W022` | `FLOW-PUB-01` | Migrate to Course Catalog & Sessions; preserve safe GET filters, Thai labels, attachments through Documents & Consent. |
| `W009-W010` | `FLOW-NOTIFY-01` | Local-only preview adapter, Notifications; no applicant data or production route. |
| `W011`, `W014`, `A003-A007` | `FLOW-AUTH-01` | Migrate to Identity & Access / Documents & Consent; replace enumeration with non-disclosing challenge outcome. |
| `W012-W013`, `A002`, `W015`, `W018` | `FLOW-AUTH-02`, `FLOW-AUTH-03` | Migrate. Legacy password verifier is a one-way rehash/recovery adapter. |
| `W016-W017`, `W023-W026` | `FLOW-MSG-01`, `FLOW-INV-02` | Typed outcome/token exchange adapter; POST confirmation/cancel/withdraw command only. |
| `W019-W020` | `FLOW-REF-01` | Migrate to Reference Data read contract. |
| `W021`, `W027-W041`, `W045` | `FLOW-APP-01..05` | Migrate guided flow; adapter may resume a safe legacy context only after ownership validation. |
| `W046-W051` | `FLOW-APP-06` | Temporary Legacy Compatibility adapter to Application Workflow; translate allowlisted payload fields/semantic keys only. |
| `W052-W062` | `FLOW-INV-01` | Temporary Legacy Compatibility token exchange to Invitations & Confirmations; no direct status write. |
| `W063`, `W091-W092`, `W098` | `FLOW-ADMIN-01`, `FLOW-DEBUG-01` | Migrate admin authentication/landing; placeholder removed or replaced by an approved projection. |
| `W064-W067` | `FLOW-REVIEW-01` | Migrate to Review & Selection; decision asks Application Workflow for authorized transition. |
| `W068-W070`, `W073`, `W075` | `FLOW-COURSE-01` | Migrate to Course Catalog & Sessions/Application Workflow; bulk command requires preview, idempotency, per-item result. |
| `W071-W072` | `FLOW-OPS-01` | Migrate to Operations & Facilities projection and Reports & Exports adapter. |
| `W074`, `W099`, commands `request:confirm`, `invite:accept` | `FLOW-INV-03`, `FLOW-MAINT-01` | Replace with Notifications outbox/reminder policy; command adapters disabled until production task inventory approves. |
| `W076-W081`, `A010` | `FLOW-ADMIN-02` | Migrate to Identity & Access; POST/DELETE only, scoped access, audit, no enumeration. |
| `W082-W083` | `FLOW-PAROLE-01` | Archive candidate only. No new module or redirect until product owner validates purpose, data, access, retention, and telemetry. |
| `W084`, `W093-W097`, `A008` | `FLOW-CHECKIN-01` | Migrate to Check-in & Attendance; authenticated course-scoped minimal-data query; manual fallback. |
| `W086-W087` | `FLOW-REPORT-01` | Migrate to Reports & Exports with one projection and golden eight-sheet fixture. |
| `W088-W090` | `FLOW-REPORT-02` | Migrate to Reports & Exports with five-group projection and `0/1/10/11` print fixture. |

This ledger covers every `W001-W099` and `A001-A010` by range. Exact route/path assertions remain in `docs/rebuild/current-page-inventory.csv`; a validator must fail when an ID lacks exactly one row above.

## Artifact disposition

| Artifact class | Flow IDs | Contract |
|---|---|---|
| 91 Blade files and 14 active components/partials | all rendered flows | Migrate by page-owner mapping; inactive/orphan files are archive candidates until telemetry/owner/retention decision. |
| 8 root HTML and 2 root PHP artifacts | `FLOW-ART-01`, `FLOW-DEBUG-01` | Archive candidate; do not serve in target without named owner and security review. |
| 12 public PDFs and course attachments | `FLOW-PUB-01`, `FLOW-ART-01` | Documents & Consent version/checksum/visibility record plus compatibility redirect. |
| 13 email templates | `FLOW-NOTIFY-01`, `FLOW-INV-01..03` | Versioned Notifications recipe; fake provider snapshots before activation. |
| dormant `NewFlow` and Parole code | `FLOW-ART-01`, `FLOW-PAROLE-01` | Preserve evidence only; remove after production owner/telemetry/rollback acceptance. |

## Shared-aggregate coexistence matrix

| Aggregate | Before cohort switch | During selected cohort | After target write | Cutover signal |
|---|---|---|---|---|
| Account/credential | Legacy sole writer; target reads through adapter. | Target writes only enabled auth cohort; legacy remains read-only for that cohort. | Identity & Access sole writer. | supported hashes transitioned/recovered and reconciliation passes |
| Person/profile/training | Legacy sole writer. | One writer selected per person before route switch; target reads imported snapshot/current projection. | People & Profiles sole writer. | source checksums and conflict queue clear |
| Reference/course/document | Legacy read source; target import/read model. | Target owns selected cohort records after checksum and URL redirect validation. | Target owner sole writer. | stable keys/checksums/redirect inventory pass |
| Application/review/invitation | Legacy sole writer. | Entire course session switches atomically; never split an Application lifecycle. | Target owning modules sole writers. | final delta/reconciliation and route switch |
| Reports/exports | Legacy oracle only. | Target generates shadow output; no target operational action from stale projection. | Reports & Exports sole artifact writer. | approved golden equality/difference record |

Read-through, one-way ETL, outbox-fed projections, and explicit ownership switch are allowed. Bidirectional dual-write is prohibited.

## Deterministic compatibility simulations

- Route-ledger test: exactly 109 endpoint IDs resolve to one disposition; unsafe IDs resolve without domain mutation.
- Token test: valid/expired/replayed/owner-mismatched synthetic legacy token produces safe deterministic outcomes.
- Payload test: known numeric field maps to semantic key; unknown/duplicate/malformed value quarantines.
- Password test: supported rehash and unsupported forced recovery are non-enumerating.
- Coexistence test: one writer per aggregate/cohort; attempted second writer fails before persistence.
- Artifact test: document checksum/redirect and email/template fake snapshot preserve the approved fixture result.

Production-only exclusions: actual route/link/PDF/email use, traffic/referrer telemetry, active token population, legal retention, artifact owner decision, deadline date, and product/engineering approval. Thus this does not pass production `G3` or authorize retirement.
