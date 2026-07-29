# Delivery Progress

Last updated: 2026-07-29 15:18 +07

Owner: PM/controller. This is the presentation-ready delivery dashboard. Update
at each merge, blocker, replacement SHA, runtime change, and hourly checkpoint.
GitHub Issues remain the task-level source of truth.

## Executive status

- Goal: complete all 32 local Tapoda Next scopes with dual exact-SHA review and
  a verified Docker application at `http://localhost:8080`.
- Explicit exclusions: production approvals, production writes, cutover, and
  destructive legacy retirement.
- Scope tracker: 12 closed / 20 open.
- Default-branch release: PR #43 merged at
  `b3ff3825e289d40b0d202d077c7332d2255cc175`; reviewed head `dda4177`,
  duplicate exact-SHA CI runs both passed 4/4, and dual reciprocal approval is
  complete. It delivers Course Catalog, the PHPStan gate, PM docs, and the
  guarded local seed after PR #39/#40.
- Current local visible runtime: `http://localhost:8080` serves exact `main`
  merge `b3ff3825` using content-addressed image
  `sha256:f4cf9c5cea8b340ca38d812dc65defdc6e5dbd3863e38f57100f1beaf6f1a6b7`.
  Fresh local migration/seed, artifact assertion, readiness, smoke, Catalog
  content checks, and seeded HTTP sign-in pass.

## User check URLs

| URL | Expected result | Verified |
| --- | --- | --- |
| `http://localhost:8080/` | Thai system-state page | HTTP 200 |
| `http://localhost:8080/course?year=2026` | Seeded Course Catalog for 2026 | HTTP 200; three seeded course links found |
| `http://localhost:8080/course/detail/D10-2026-08-TAPODA` | Seeded course detail, policy, and document status | HTTP 200; code/document content found |
| `http://localhost:8080/signup` | Create a local account | HTTP 200 |
| `http://localhost:8080/signin` | Sign in with the seeded account below | HTTP 200 |
| `http://localhost:8080/forgot` | Start local password recovery | HTTP 200 |
| `http://localhost:8080/account` | Account security page; redirects to sign-in when anonymous | HTTP 302 to `/signin` |
| `http://localhost:8080/health/live` | Process liveness JSON | HTTP 200 |
| `http://localhost:8080/health/ready` | PostgreSQL, Redis, worker, and scheduler readiness JSON | HTTP 200 |

Supported local-only seeded account:

- Sign-in ID: `1234567890123`
- Password: `TapodaLocalSeed!2026`
- Recovery email: `local-seed-account@tapoda.test`

PR #42 exact head `478a5a734fb7ccec9a0e835dbcd1076b104091a3`
passed a fresh isolated PostgreSQL seed, readiness/smoke, real HTTP sign-in to
`/account`, CI 4/4, and dual reciprocal review. It merged to integration at
`a9389ac23de2e6eaaadefe3f4279b17a6c6c9f1e`; Issue #36 is closed. On the
exact `main` runtime, `POST /signin` returned HTTP 200 with redirect `/account`,
and the authenticated `GET /account` returned HTTP 200.

## Release train

| Order | Increment | State | Exact evidence | Next action |
| --- | --- | --- | --- | --- |
| 1 | Foundation + Account to `main`; remove `.scratch/tapoda-rebuild/` | Delivered | PR #39 merge `8169f11`, reviewed `71bf9af`, CI 4/4, dual reciprocal PASS | Keep as release baseline |
| 2 | PM operating system | Delivered | PR #40 merge `9749eaec`, reviewed `daf9cd9`, CI 4/4, dual reciprocal PASS | Keep dashboard authoritative |
| 3 | Course Catalog canonical ownership | Delivered to `main`/`:8080`; Issue #10 closed | PR #34 merge `e675535`, PR #43 merge `b3ff382`, Browser 26/26, local Catalog checks pass | Keep as release baseline |
| 4 | Deterministic PHPStan memory gate | Delivered to `main` | PR #38 merge `762ff4c`, PR #43 merge `b3ff382`, CI 4/4 | Keep as release gate |
| 5 | Safe canonical local seed | Delivered to `main`/`:8080`; Issue #36 closed | PR #42 merge `a9389ac`, PR #43 merge `b3ff382`, fresh local seed/sign-in pass | Keep credentials and reset instructions current |
| 6 | Profile/application security | Implementation active | Issue #12 comment `5114789252`; backend/UI candidate exists | Focused/static/browser gates |

## Active workstreams

| Workstream | Owner | Status | Blocker | Next checkpoint |
| --- | --- | --- | --- | --- |
| Release control | PM/controller | PR #43 merged at `b3ff382`; exact release live on `:8080` | None | Publish runtime evidence through PR #45 |
| PM operating system | PM/controller | Delivered and active | None | Refresh dashboard at every phase boundary |
| Course Catalog | coding + dual review agents complete | Released to `main`/`:8080`; URLs verified above | None | Keep regression gates |
| Local seed | coding/review agents complete | Released to `main`/`:8080`; real seeded sign-in verified | None | Keep local-only guard |
| PHPStan gate | coding/review agents complete | Released to `main`; Issue #37 closed | None | Keep deterministic wrapper gate |
| Issue #12 member center | coding agent active | Implementation task packet posted at Issue comment `5114102073`; isolated branch from integration `e675535` | Must not overlap PR #42 seed files | Produce focused local candidate and issue checkpoint |

## Delivery metrics

- Observed first 11 hours: 10/32 scopes closed; three implementation slices
  reached done or review-ready.
- Naive scope velocity is misleading because #1–#8 are decision gates and later
  lifecycle, migration, report, and staff scopes are larger.
- Current forecast for local completion: 96–120 continuous hours at the
  observed quality bar; 12–15 eight-hour working days.
- Release policy: no completed vertical slice waits for all 32 scopes. Target a
  default-branch release every 60–90 minutes when an independently usable slice
  has green gates and dual approval.

## Current bottlenecks and controls

1. Reviewer/runtime queue, not coding headcount.
   - Control: start reviewers after local candidate gates; add reviewer capacity
     when two candidates wait.
2. Shared schema, providers, CSS, Compose, and browser infrastructure.
   - Control: serialize conflicting work; parallelize only disjoint modules.
3. Replacement-SHA review loops.
   - Control: integrate dependencies first, run the final exact runtime, then
     begin review; every new commit invalidates old verdicts.
4. Progress hidden on feature ports or integration branches.
   - Control: release small slices to `main`, rebuild `localhost:8080`, and
     update this file at every phase boundary.

## PM reporting format

Every status response is generated from this dashboard and contains:

1. Delivered since last report.
2. Active work by owner.
3. Current bottleneck and evidence.
4. Release/runtime state.
5. Next measurable target and forecast.

Before publishing or committing this dashboard, refresh GitHub Issue, PR, CI,
branch, agent, and runtime state. A timestamp without an authoritative state
refresh is invalid.
