# Delivery Progress

Last updated: 2026-07-29 12:56 +07

Owner: PM/controller. This is the presentation-ready delivery dashboard. Update
at each merge, blocker, replacement SHA, runtime change, and hourly checkpoint.
GitHub Issues remain the task-level source of truth.

## Executive status

- Goal: complete all 32 local Tapoda Next scopes with dual exact-SHA review and
  a verified Docker application at `http://localhost:8080`.
- Explicit exclusions: production approvals, production writes, cutover, and
  destructive legacy retirement.
- Scope tracker: 10 closed / 22 open.
- Default-branch release: PR #39 merged at
  `8169f11ec16ca9d821bc07be67deb042bed182c3`; reviewed head `71bf9af`, CI
  4/4, dual reciprocal approval complete. Tracked `.scratch/tapoda-rebuild/`
  is removed from `main`.
- Current local visible runtime: `http://localhost:8080` still serves the
  previously released foundation until Course Catalog PR #34 is merged and the
  stack is rebuilt.

## Release train

| Order | Increment | State | Exact evidence | Next action |
| --- | --- | --- | --- | --- |
| 1 | Foundation + Account to `main`; remove `.scratch/tapoda-rebuild/` | Delivered | PR #39 merge `8169f11`, reviewed `71bf9af`, CI 4/4, dual reciprocal PASS | Keep as release baseline |
| 2 | PM operating system | Review finding addressed in current branch | PR #40; acceptance concern `5113786347` | Push replacement, CI, dual review, merge |
| 3 | Course Catalog canonical ownership | CI browser failed; coding fix queued | PR #34 `c7f0da0`; run `30425893895`, job `90492189747`; auth signup 5% and catalog 1% Linux snapshot mismatch | Reproduce exact CI merge, inspect both diffs, run full 26-test browser suite, push replacement |
| 4 | Deterministic PHPStan memory gate | Draft PR; CI green | PR #38, `af4f6ff`, CI 4/4 | Dual review, merge |
| 5 | Safe canonical local seed | Local review fix complete | Issue #36, `8ee0b78`, full gates and seeded runtime pass | Push, PR, CI, dual review |
| 6 | Profile/application security | Not started | Issue #12 | Start after #34 schema lands |

## Active workstreams

| Workstream | Owner | Status | Blocker | Next checkpoint |
| --- | --- | --- | --- | --- |
| Release control | PM/controller | PR #39 merged to `main` | None | Publish PM operating-system slice |
| PM operating system | PM/controller | PR #40 acceptance finding addressed | Replacement CI/review | Push current branch and obtain dual reciprocal PASS |
| Course Catalog | coding agent | Backend/frontend/secrets pass; browser failed | Two integrated Linux visual baselines do not match exact CI | Address PR comment `5113783883`, then rerun full browser CI |
| Local seed | coding agent | Security guard fixed; local gates pass | PR not created | Publish issue #36 branch |
| PHPStan gate | coding agent complete | PR #38 CI 4/4 | Reviewer capacity | Review PR #38 |

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
