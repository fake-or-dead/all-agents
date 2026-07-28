# Local production-baseline decision

**Decision ID:** `LOCAL-G0-BASELINE`
**Applies to:** `G0` production truth; supports `G1`, `G3`, `G8`, and `G9`
**Source repository evidence:** Tapoda `uat-20260526` commit `3d2c3a4b843f73cf0b19c5cb9a4d2e54e80d78aa`
**Status:** Local implementation baseline accepted; production baseline not accepted

## Decision

Use a sanitized, deterministic *production-shape* fixture as the only local input. It represents static repository evidence, not live data. Every local implementation and migration test must identify its source as `repository-static/3d2c3a4`, set `production_evidence=false`, and keep source keys synthetic.

This decision covers `FLOW-AUTH-01` to `FLOW-AUTH-03`, `FLOW-APP-01` to `FLOW-APP-06`, `FLOW-INV-01` to `FLOW-INV-03`, `FLOW-REVIEW-01`, `FLOW-COURSE-01`, `FLOW-CHECKIN-01`, `FLOW-OPS-01`, `FLOW-REPORT-01` to `FLOW-REPORT-02`, `FLOW-MAINT-01`, `FLOW-NOTIFY-01`, `FLOW-DEBUG-01`, and `FLOW-ART-01`.

## Local evidence register

| Evidence | Local conclusion | Gate use |
|---|---|---|
| `routes/web.php`, `routes/api.php` | 99 web IDs `W001-W099`, 10 API IDs `A001-A010`, and two console commands are statically reconciled in `docs/rebuild/coverage-matrix.md`. | Compatibility fixture input only. |
| Models, controllers, six Laravel migrations, 19 `db_scripts` files | Repository schema is incomplete and cannot define deployed DDL. | Never generate target constraints from it alone. |
| `docs/rebuild/current-flow-ledger.md` | Current behavior is `verified-code`, `verified-artifact`, or explicitly `unknown-production`; it is not runtime evidence. | Characterization scenarios. |
| `docs/rebuild/module-blueprint.md` | Target ownership and transitional seams are locally decided. | Target mapping contract. |

No source extracts containing a real person, personal identifier, email, telephone number, address, credential, token, or secret may enter a fixture, log, report, issue, or commit.

## Required local fixture pack

`fixtures/legacy-shape/v1/` is the reserved implementation location. It must contain synthetic IDs only and a manifest with fixed clock `2026-07-29T00:00:00+07:00`, locale `th-TH`, timezone `Asia/Bangkok`, deterministic UUID/ULID seed, and SHA-256 checksum per file.

| Fixture | Covers | Required cases |
|---|---|---|
| `accounts-people` | `FLOW-AUTH-01..03`, `FLOW-MEMBER-01` | active/inactive account, supported legacy hash, unsupported hash, duplicate identifier quarantine, null/empty address fields. |
| `reference-course-form` | `FLOW-PUB-01`, `FLOW-REF-01`, `FLOW-APP-01..05` | Thai sort, course/center/type keys, closed/open windows, every conditional semantic key, missing reference. |
| `applications` | `FLOW-APP-01..06`, `FLOW-REVIEW-01`, `FLOW-COURSE-01` | each legacy state, `accepted`, contradictory invitation/confirmation evidence, per-course history, stale expected version. |
| `answers-consent-training` | `FLOW-APP-02..05`, `FLOW-REPORT-01..02` | numeric question/choice mapping, unknown question, duplicate answer, immutable label snapshot, alumni and staff-applicant evidence. |
| `invitations-actions` | `FLOW-INV-01..03`, `FLOW-MAINT-01`, `FLOW-NOTIFY-01` | valid, expired, replayed, owner-mismatch, delivery failure, resend cooldown, no provider call on rejected transition. |
| `attendance-operations` | `FLOW-CHECKIN-01`, `FLOW-OPS-01` | confirmed-only lookup, course-scope denial, duplicate scan, manual/card unavailable/mismatch, facilities/laundry days `01`-`08`. |
| `reports-documents-artifacts` | `FLOW-REPORT-01..02`, `FLOW-ART-01` | eight applicant groups, five teacher groups, `0/1/10/11` print selections, public/private document URL, missing artifact. |

The pack must include no production cardinalities. It exercises shape, not volume. A production profile later supplies distributions and volume bands without copying personal data.

## Local simulations and pass conditions

| Simulation | Deterministic pass condition | Gates |
|---|---|---|
| ETL replay twice with same batch ID | Same target IDs/checksums; second run has zero duplicate writes. | `G1`, `G9` |
| Quarantine replay | Each invalid row has stable reason, owner module, source reference, and no invented replacement value. | `G1` |
| Legacy token exchange | Valid synthetic legacy token becomes one hashed, expiring, one-use action token; expired/replayed/mismatched token does not disclose existence. | `G3` |
| Password bridge | Supported synthetic legacy hash rehashes after authentication; unsupported hash requires recovery. | `G3` |
| Report oracle | Screen, print, and XLSX derive the same approved fixture projection and preserve Thai ordering. | `G3`, `G9` |
| Cohort cutover rehearsal | Freeze, final delta, checksum, traffic switch, read-only legacy projection, and forward recovery are recorded with a fixed simulated clock. | `G8`, `G9` |

## Production-only evidence and signoffs excluded

The following are not locally verifiable and are **excluded**: live DDL, indexes, collations, row counts, data quality, production route/redirect behavior, bookmarks, active links, email delivery, scheduled/manual operations, workstation inventory, provider configuration, data residency, backup region, RPO/RTO, capacity, cost, SSL provider/access/renewal/ownership (`CLARIFY-006`), telemetry, and data-owner/platform-owner/delivery-owner acceptance.

Therefore this document does not pass production `G0`, `G8`, or `G9`; it fixes the safe local test boundary only.
