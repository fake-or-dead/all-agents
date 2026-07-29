# Delivery Progress

Last updated: 2026-07-29 14:12 +07

Owner: PM/controller. This is the presentation-ready delivery dashboard. Update
at each merge, blocker, replacement SHA, runtime change, and hourly checkpoint.
GitHub Issues remain the task-level source of truth.

## Executive status

- Goal: complete all 32 local Tapoda Next scopes with dual exact-SHA review and
  a verified Docker application at `http://localhost:8080`.
- Explicit exclusions: production approvals, production writes, cutover, and
  destructive legacy retirement.
- Scope tracker: 10 closed / 22 open.
- Default-branch release: PR #40 merged at
  `9749eaec91e154584366bc64261e9fb3d2c6d5e2`; reviewed head `daf9cd9`, CI
  4/4, dual reciprocal approval complete. It follows PR #39, which delivered
  Foundation + Account and removed tracked `.scratch/tapoda-rebuild/`.
- Current local visible runtime: `http://localhost:8080` serves exact `main`
  merge `9749eaec` using content-addressed image
  `sha256:a0de2d18c138cb560aa2ef111aaa71652df7befc2adc1ffc47feb28362b2b8ca`.
  Migration, runtime artifact assertion, readiness, and smoke pass.

## User check URLs

| URL | Expected result | Verified |
| --- | --- | --- |
| `http://localhost:8080/` | Thai system-state page | HTTP 200 |
| `http://localhost:8080/signup` | Create a local account | HTTP 200 |
| `http://localhost:8080/signin` | Sign in with an account created locally | HTTP 200 |
| `http://localhost:8080/forgot` | Start local password recovery | HTTP 200 |
| `http://localhost:8080/account` | Account security page; redirects to sign-in when anonymous | HTTP 302 to `/signin` |
| `http://localhost:8080/health/live` | Process liveness JSON | HTTP 200 |
| `http://localhost:8080/health/ready` | PostgreSQL, Redis, worker, and scheduler readiness JSON | HTTP 200 |

There is no supported seeded account on this release. The current `main`
seeder still references removed `App\Models\User`; do not run it. Create an
account through `/signup`. Issue #36 contains the guarded canonical local seed
candidate and will publish its user/password here after PR #34 is integrated.
Course Catalog URLs are not listed as delivered until PR #34 passes integrated
browser CI, dual review, merge, and the `localhost:8080` rebuild.

## Release train

| Order | Increment | State | Exact evidence | Next action |
| --- | --- | --- | --- | --- |
| 1 | Foundation + Account to `main`; remove `.scratch/tapoda-rebuild/` | Delivered | PR #39 merge `8169f11`, reviewed `71bf9af`, CI 4/4, dual reciprocal PASS | Keep as release baseline |
| 2 | PM operating system | Delivered | PR #40 merge `9749eaec`, reviewed `daf9cd9`, CI 4/4, dual reciprocal PASS | Keep dashboard authoritative |
| 3 | Course Catalog canonical ownership | Replacement CI 25/26; native baseline provenance fix in progress | PR #34 `d77ab7b`; run `30430189557`, browser job `90505399783`; native Catalog actual is stable `b816107d`, inspected with no overflow/clipping/lost content | Replace only Catalog baseline from exact CI actual, rerun 26/26 |
| 4 | Deterministic PHPStan memory gate | Delivered to integration | PR #38 merge `762ff4c`, reviewed `c62ae43`, CI 4/4, dual reciprocal PASS | Include in next `main` release |
| 5 | Safe canonical local seed | Local review fix complete | Issue #36, `8ee0b78`, full gates and seeded runtime pass | Push, PR, CI, dual review |
| 6 | Profile/application security | Not started | Issue #12 | Start after #34 schema lands |

## Active workstreams

| Workstream | Owner | Status | Blocker | Next checkpoint |
| --- | --- | --- | --- | --- |
| Release control | PM/controller | PR #39 and PR #40 merged to `main`; exact release live on `:8080` | None | Release Course Catalog when green |
| PM operating system | PM/controller | Delivered and active | None | Refresh dashboard at every phase boundary |
| Course Catalog | coding + dual review agents | Auth CSS/clock fixes pass; CI 25/26 | Local-emulated Catalog baseline is not native CI pixel truth | Exact native baseline replacement, fresh CI/reviews |
| Local seed | coding agent | Security guard fixed; local gates pass | PR not created | Publish issue #36 branch |
| PHPStan gate | coding/review agents complete | PR #38 merged at `762ff4c`; Issue #37 closed | None | Ship with next release |
| Issue #12 member center | unassigned | Implementation task packet posted at Issue comment `5114102073` | Start condition is PR #34 merge | Branch from resulting integration SHA |

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
