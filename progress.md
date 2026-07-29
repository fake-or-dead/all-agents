# Delivery Progress

Last updated: 2026-07-29 20:07 +07

Owner: PM/controller. This is the presentation-ready delivery dashboard. Update
at each merge, blocker, replacement SHA, runtime change, and hourly checkpoint.
GitHub Issues remain the task-level source of truth.

## Executive status

- Goal: complete all 32 local Tapoda Next scopes with dual exact-SHA review and
  a verified Docker application at `http://localhost:8080`.
- Explicit exclusions: production approvals, production writes, cutover, and
  destructive legacy retirement.
- Scope tracker: 12 complete / 20 open.
- Default-branch release: PR #55 merged at
  `dff85f6437a0bdddfe6e86e982a15cce5dd3af9c`; reviewed head `a878957`,
  duplicate exact-SHA CI runs both passed 4/4, and security/acceptance reciprocal
  approval is complete. It adds Member Center to the prior Course Catalog,
  PHPStan gate, PM docs, and guarded local seed.
- Current local visible runtime: `http://localhost:8080` serves exact `main`
  merge `dff85f6` using content-addressed image
  `sha256:0cb3bc1da6e89be1f5ce26ad3b64d96f232ab8b47d07102694d6005297bc2818`.
  Additive migration/seed, artifact assertion, readiness, smoke, seeded HTTP
  sign-in, and all four authenticated Member URL checks pass.
- Integration head `a8789578274d0b876914b2f5721c37573cde5f87`
  is tree-equivalent to the reviewed release head. Issue #50 has started from
  this exact base as the next 60–90 minute child slice.

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
| `http://localhost:8080/member/profile` | Member profile | Authenticated HTTP 200 |
| `http://localhost:8080/member/training` | Training history | Authenticated HTTP 200 |
| `http://localhost:8080/member/applications` | Application status | Authenticated HTTP 200 |
| `http://localhost:8080/member/password` | Password security | Authenticated HTTP 200 |
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
| 6 | Profile/application security | Delivered to `main`/`:8080`; Issue #12 closed | PR #48 merge `e34bf6c`; PR #55 merge `dff85f6`; reviewed release `a878957`; CI 8/8; exact image `0cb3bc1d...`; sign-in and four Member URLs pass | Keep regression gates |

## Active workstreams

| Workstream | Owner | Status | Blocker | Next checkpoint |
| --- | --- | --- | --- | --- |
| Release control | PM/controller | PR #55 merged at `dff85f6`; exact Member Center release live on `:8080` | None | Publish this release evidence, then keep small-release cadence |
| PM operating system | PM/controller | Delivered and active | None | Refresh dashboard at every phase boundary |
| Course Catalog | coding + dual review agents complete | Released to `main`/`:8080`; URLs verified above | None | Keep regression gates |
| Local seed | coding/review agents complete | Released to `main`/`:8080`; real seeded sign-in verified | None | Keep local-only guard |
| PHPStan gate | coding/review agents complete | Released to `main`; Issue #37 closed | None | Keep deterministic wrapper gate |
| Issue #12 member center | coding + dual review agents complete | Released to `main`/`:8080` at `dff85f6`; Issue #12 closed; exact artifact and authenticated URL checks pass | None | Keep regression gates |
| Issue #13 application | PM + #50 coding/early-review agents | #50 active from exact `a878957`; four of six early findings fixed locally; #57 now tracks server-owned eligibility/persona evidence and blocks #51 | Two #50 review findings plus final gates | Deliver #50 schema/resolver slice; no routes/UI/drafts; do not assign #51 |
| Issues #14–#32 | PM decomposition complete | #14–#29 have PR-sized decomposition comments; #30–#32 are explicit XL multi-PR local packets with production actions separated | Each waits for predecessor; child Issues created just in time | Never assign original XL packet as one batch |
| Docker lifecycle | PM/controller | `issue36seed` stopped project removed with volume preserved; `tapoda-next` is live; only PostgreSQL/Redis of `tapoda-issue12-browser` are leased to the #50 coding agent's service gates | Lease owner and active consumer: #50 coding agent | Query that agent before teardown; inventory normal plus `tools`/`restore` profiles; down candidate only after explicit lease release |

## Delivery metrics

- Current scope progress: 12/32 scopes complete. Member Center is released and
  verified on `localhost:8080`; child #50 is the active next slice.
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
