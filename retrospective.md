# Retrospective

Owner: PM/controller. Update at every fixed hourly checkpoint without waiting
for a user prompt, and immediately when a material delivery, architecture,
testing, review, or runtime problem changes the plan.

## 2026-07-29 — Local-complete delivery

### Problems found

- Visible progress lagged behind engineering progress. Platform work merged, but `localhost:8080` still showed only the base application while feature branches ran on separate ports.
- Review began before the feature stack was integrated. PR #34 depended on the canonical account model from PR #35, causing avoidable replacement-SHA review cycles.
- Review feedback arrived in several rounds. Each replacement SHA invalidated both previous verdicts and required two fresh exact-SHA reviews.
- Security and acceptance reviewers became the bottleneck while coding agents waited.
- Work was preplanned too far ahead (#12–#32) before #10 and #11 were merged and visible.
- `localhost:8000` remained a legacy PHP/MySQL runtime and returned HTTP 500, while Tapoda Next used `localhost:8080`. The two URLs were not made clear enough.
- `.scratch/` appeared in pushes before it was removed from tracking and ignored. GitHub Issues are the canonical task tracker; local scratch issue files created duplicate noise.
- PR #34 introduced a temporary ownership projection without a real writer, backfill, synchronization, or freshness guarantee. Cross-PR schema ownership was not resolved before implementation.
- Runtime Caddy configuration overwrote the application `Referrer-Policy`, so application-only header tests missed deployed behavior.
- Public catalog localization was fixed on the detail view but not consistently applied to list dates and filter labels.
- PR #35 security review found race, key-separation, immutable-consent, bearer-token leakage, bcrypt-cost coverage, and stale architecture-document risks after the first fix cycle.
- PR #35 acceptance review found proof-expiry feedback and accessibility/responsive/error-matrix gaps after the first fix cycle.

### Decisions

- Critical path: merge Identity & Access (#11), rebase and merge Course Catalog (#10), then rebuild `localhost:8080`.
- Stop preparing later issues until current features are merged and visible.
- Build dependent features on canonical schemas. Do not duplicate ownership tables or temporary projections across PRs.
- Review exact candidate SHAs only after dependency integration and full local gates pass.
- Post every concern on the PR before fixes. After a push, invalidate old verdicts and repeat both reviews against the new exact SHA.
- Test runtime behavior through Docker/Caddy, not only framework-level responses.
- Keep production approvals, production writes, cutover, and destructive retirement excluded.

### Process changes

- Deliver vertical slices to `localhost:8080` frequently so progress is directly inspectable.
- Track issue state and agent work in GitHub Issues and PRs only.
- Limit work in progress to branches that can merge without unresolved schema dependencies.
- Use additional coding agents only for non-overlapping files or independent scopes.
- Add reviewer capacity when review queue, not coding, is the measured bottleneck.
- Reserve final `gpt-5.6-sol` xhigh security and acceptance review for integrated candidates above 80% completion.
- Update this file whenever a new delivery, architecture, testing, review, or runtime problem is found.
- Separate PM state from learning and standards: `progress.md` is the current
  presentation dashboard, `retrospective.md` is the hourly learning record, and
  `shared_knowledge.md` is the reusable agent standard.
- The PM/controller owns release cadence and bottleneck control, not passive
  status collection. Release reviewed vertical slices to `main` every 60–90
  minutes when ready.
- Each assigned agent posts its own phase-boundary GitHub Issue updates. The PM
  presents from `progress.md` instead of reconstructing status on demand.
- Every new repeated failure becomes a shared standard or task-packet rule so
  the next coding agent does not repay the same learning cost.
- The first `progress.md` draft copied a stale PR #34 pending state even though
  exact browser CI had already failed. A dashboard timestamp is not evidence.
  The PM must refresh Issues, PR heads, CI, reviews, agents, and runtimes before
  every dashboard commit or presentation.

## Hourly checkpoints

Every active delivery hour records:

1. Merged or directly inspectable output.
2. Current bottleneck and evidence.
3. Loop or process failure discovered.
4. Process change applied immediately.
5. One measurable target for the next hour.

### 2026-07-29 10:07 +07

- Output: PR #35 second-remediation work split into isolated security/backend and acceptance/UI worktrees. Security patch reached local commit `3138fee`; focused Docker tests previously passed 20 tests / 153 assertions. Acceptance patch is in progress. Nothing new is merged or visible on `localhost:8080` yet.
- Bottleneck: PR #35 remains the merge dependency for PR #34 and the visible integration runtime.
- Loop found: the first atomic rate-limit fix locked the combined client/identifier/pair key set. Requests sharing only a client or only an identifier could use different locks and still race on a shared bucket.
- Change applied: rejected the candidate before push. Required an action-scoped lock or correctly ordered per-bucket/Lua atomic operation plus parallel tests across changing identifiers and changing IPs.
- Test-environment problem found: PHPUnit forced `CACHE_STORE=array`, so an initial forked concurrency test appeared green without sharing counters. The test now explicitly selects Compose Redis, clears inherited facades/sockets in every child, and proves only one callback crosses an overlapping client-bucket ceiling.
- Acceptance problem found: full-document axe testing exposed `.button-secondary:hover` contrast at 1.43. Main-only axe checks had hidden this header/navigation state. The shared style was corrected and the full-document matrix retained.
- Test-design problem found: one long keyboard-only journey was brittle and obscured the failing screen. It was split into deterministic keyboard input/Enter journeys per screen with explicit state assertions.
- Runtime problem found: adding a recovery-specific Caddy header block was insufficient because the global header block still executed and overwrote `no-referrer`. Recovery and non-recovery policies now use disjoint matchers, and `bin/smoke` verifies the final Docker response headers.
- CI-parity problem found: local full PHP gates ran inside Compose with PostgreSQL and Redis, while the backend CI job ran a standalone SQLite/no-Redis test image. Two real-service proofs passed locally but failed CI before assertions. Ordinary tests must skip unavailable integration dependencies, and CI must contain a separate required PostgreSQL/Redis integration step that cannot silently skip.
- Next-hour target: integrate both PR #35 commits, pass focused and full local gates, and produce one replacement SHA ready for dual exact-SHA review.

### 2026-07-29 11:07 +07

- Output: PR #35 replacement head `617997bc89df72cd5e580b4d7dd00d585554a8cd` is pushed. Exact runtime image `sha256:845d80aa4afc525c26821ed16bcf86323710728ceeb0dac0d80cbd8082aa6cd1` passed artifact assertions and smoke at `http://127.0.0.1:18114`. GitHub CI is 4/4 green. Dual exact-SHA security and acceptance review has started. Nothing new is merged or visible on `localhost:8080` yet.
- Bottleneck: independent xhigh review and cross-review are now the only PR #35 merge gate.
- Loop found: the first CI run exposed missing service topology; the replacement CI run then failed both Docker builds before tests because PECL returned HTTP 504 for `redis-6.3.0.tgz`. The same SHA passed on one bounded rerun, proving infrastructure failure rather than repository behavior.
- Change applied: backend CI now separates portable SQLite tests from a required six-test PostgreSQL/Redis group that fails instead of skipping when services are absent. Upstream build outages receive one same-SHA rerun; repeated failure requires deterministic Docker build hardening instead of repeated retries.
- Exact-SHA review found: the action-global Redis mutex fixed counter races but serialized unrelated users and could produce false 429 responses. Replace it with privacy-safe bucket locks acquired in deterministic global order; overlapping buckets serialize while unrelated subjects proceed concurrently.
- Exact-SHA review found: configuring current and previous lookup keys with the same version silently collapsed the PHP associative map before validation. Preserve previous-version metadata and reject duplicate versions before building the keyring.
- Exact-SHA review found: `person_account_link_proofs` was missing from the People architecture ownership gate. Add the table and a failing cross-module access fixture.
- Exact-SHA review found: the claimed keyboard-only tests focused locators directly and still used click/select helpers, so natural Tab order was not proven. Use real Tab/Shift+Tab traversal from a stable focus point and assert each focus transition.
- Exact-SHA review found: proof expiry could increment the shared request generation after `POST /signup` committed, causing the client to discard a successful redirect. Separate verification generation from in-flight signup state and test delayed success/rejection responses across expiry.
- Change applied: the five Medium findings were posted and cross-reviewed on PR #35. Security and acceptance fixes now run in separate worktrees with non-overlapping files, then merge into one candidate before full gates.
- Delivery result: previous next-hour target was met for candidate creation and CI, but not approval/merge. The visible-runtime objective remains incomplete.
- Next-hour target: obtain dual approval and merge PR #35, rebase and finish PR #34 on the canonical account schema, then rebuild `localhost:8080` with both Auth and Catalog.

### 2026-07-29 12:07 +07

- Output: all five prior Medium findings were fixed in local commits `32210c8` and `45398b9`. The exact-runtime backend gates passed 46 tests / 285 assertions plus 7 PostgreSQL/Redis tests / 33 assertions. The acceptance gate passed 10/10 focused browser tests. Full Playwright then produced a terminal result of 15/16, exposed one invalid test fixture, and passed 16/16 after the fixture-only fix `adab068`. GitHub CI passed 4/4 on `adab068`.
- Review output: fresh xhigh review found one additional Medium S1 key-rotation gap. The remediation commit `3a550251d5e5b61c9c3b98274e39ae9662c96c8f` is pushed and new GitHub CI is running. Before push, independent security and acceptance reviews both passed and cross-reviewed the exact local commit. Nothing new is merged or visible on `localhost:8080` yet.
- Bottleneck: PR #35 remains the serial dependency for PR #34 and the visible integration runtime. Its current merge gate is CI plus fresh remote exact-SHA verdicts for `3a55025`.
- Test-loop problem found: Playwright was first run against stale `localhost:8080`, not the exact candidate runtime. Later partial reruns reused one Redis client-global rate-limit bucket and contaminated subsequent results. Fix: bind every browser gate to an explicit per-SHA runtime URL and recovery container, run the full serial suite once, and require a captured terminal exit code.
- Agent-orchestration problem found: two agent-owned Playwright executions emitted 13/16 and 15/16 before their execution handles disappeared. Partial output is not proof. Fix: long-running terminal gates run from the root-owned persistent exec session; agents may prepare or review but cannot report success without the final exit status.
- Runtime-readiness problem found: a fresh scheduler may wait until the next minute boundary before its first heartbeat, producing a temporary readiness 503 despite healthy processes. Fix: wait through one scheduler interval and inspect heartbeat logs before classifying the runtime as defective.
- Compose-isolation problem found: a normal override containing `ports: [18117:8080]` merged with the base port list and retained `8080:8080`, risking a collision with the user-visible stack. Pre-start `docker compose config --format json` inspection caught both mappings before startup. Fix: isolated runtime overrides must use Compose `!override` for `ports` and verify the rendered port list before `up`.
- Fixture problem found: FLOW-AUTH-05 generated `E2E${Date.now()}denied`, exceeding the passport maximum of 20 characters. The application correctly rejected it. Fix: `passportFixtureIdentity()` now normalizes and bounds fixtures to `[A-Z0-9]{6,20}` with explicit assertions. The concern was posted before the coder fix; focused test passed 1/1, reviewer passed, and full Playwright passed 16/16.
- Security problem found: lookup-key rotation spanned config, `.env.example`, Compose, and bootstrap, but prior tests covered only in-memory maps and equal versions. A previous version without its key could boot, while canonical Compose could not pass either previous pair; v0 rows could become unreachable and duplicate identities/accounts could be created under v1. Fix: fail closed on partial/equal/unmapped pairs, expose and preserve both People and Account previous pairs, and add a real PostgreSQL v0-to-v1 lookup/recovery/duplicate-prevention proof. Focused config passed 13 tests / 37 assertions; PostgreSQL rotation passed 1 test / 19 assertions; the combined PostgreSQL group passed 20 tests / 127 assertions.
- Local-setup problem found: `php artisan migrate:fresh --seed --force` reaches the seeder and fails with `Class "App\Models\User" not found` at `database/seeders/DatabaseSeeder.php:20`. This is a stale default seeder unrelated to the rotation patch. Current service tests use migration-provided consent fixtures; follow-up must remove or replace the stale User seeder before claiming seed-based local setup.
- Static-analysis problem found: focused PHPStan exhausted the container's default 128 MB parallel-worker limit. The completed gate used `php -d memory_limit=1G vendor/bin/phpstan ...`. Standard local and CI commands should set a deterministic memory limit instead of relying on container defaults.
- Parallel preparation: PR #34 was inspected read-only against incoming PR #35. Actual conflict markers are limited to `app/Models/Account.php`, `bootstrap/providers.php`, `docker/frankenphp/Caddyfile`, and `tools/architecture-check.php`; `compose.yaml` and `resources/css/app.css` auto-merge but require semantic review.
- Delivery result: the previous target was not met because exact-SHA review correctly found a new rotation blocker. The blocker is fixed and reviewed locally, but PR #35 is not yet merged and `localhost:8080` remains on the older build.
- Next-hour target: finish CI and fresh dual remote approval for `3a55025`, merge PR #35, rebase and remediate PR #34 using canonical Account/People ownership, then rebuild and verify `localhost:8080`.

### 2026-07-29 13:07 +07

- Output: PR #35 Account merged into integration at `71bf9af`; Issue #11 closed. PR #39 delivered Foundation + Account to `main` at merge `8169f11` and removed all tracked `.scratch/tapoda-rebuild/`. PR #40 delivered the PM operating system at merge `9749eaec` after CI 4/4 and dual reciprocal xhigh approval.
- Visible runtime: `localhost:8080` was rebuilt from exact `main` merge `9749eaec` as image `sha256:a0de2d18c138cb560aa2ef111aaa71652df7befc2adc1ffc47feb28362b2b8ca`. Non-destructive Identity & Access migration, exact artifact assertions for web/worker/scheduler, readiness, smoke, and the user URL matrix passed. No seed was run and the PostgreSQL volume was preserved.
- Bottleneck: PR #34 Course Catalog is locally complete but GitHub browser job `90492189747` failed two integrated Linux snapshots: Auth signup 5% and catalog 1%. Backend, frontend, and secret gates passed.
- Loop found: isolated Course Catalog browser tests passed 10/10 but did not include the integrated 26-test browser suite. The candidate reached GitHub before whole-stack visual parity was proven.
- Change applied: every final candidate must run the complete integrated browser suite against the exact candidate runtime before push and review. Feature-only browser suites remain fast feedback, not release evidence.
- PM problem found: `progress.md` became stale immediately after PR #40 merged and did not contain a concrete user URL matrix or seed-account status.
- Change applied: runtime rebuild now ends with exact artifact assertion, smoke, per-URL HTTP checks, and a dashboard update containing check URLs, expected behavior, and seed credentials or an explicit no-seed statement.
- Seed problem: `main` still references removed `App\Models\User` in `DatabaseSeeder`. Issue #36 fixes canonical local seed behavior and guards it to local/testing plus the deterministic fake adapter, but it remains unpushed until PR #34 lands to avoid shared-file conflict.
- Parallel output: PR #38 initially had green CI but its wrapper dropped all caller arguments. Review posted the Medium concern before the coder fix. Replacement `c62ae43` added safe `"$@"` forwarding and a behavioral argv/space/exit-code test; CI passed 4/4, dual reciprocal review passed, PR #38 merged at `762ff4c`, and Issue #37 closed.
- Browser-loop detail: scoping Course Catalog form styles corrected the Auth page without changing the Auth baseline. The first full 26-test local run then exposed two evidence defects: both in-flight proof tests used a 1.2-second expiry that could lapse while filling the form under pinned amd64/Rosetta, and the Playwright container held a pre-update Catalog snapshot. Final browser evidence must rebuild or mount the exact current source, assert source/snapshot hashes inside the runner, and use a deterministic expiry boundary rather than wall-clock speed.
- Next-hour target: diagnose both PR #34 image diffs, pass the full integrated browser suite, push a replacement SHA, obtain exact-SHA dual review, and merge the next small release.

### 2026-07-29 14:07 +07

- Output: PR #38 replacement `c62ae43` passed CI 4/4 and dual reciprocal review, merged into integration at `762ff4c`, and Issue #37 closed. Issue #12 received an implementation-ready task packet at comment `5114102073`; coding remains intentionally gated on PR #34 merge.
- Course output: the global Course Catalog label rule was scoped away from Auth, Auth screenshots stayed unchanged, and both in-flight proof-expiry cases now use a fixed Playwright clock plus a test-owned response-release promise. Focused clock tests passed 2/2; native full browser passed 26/26 without retries; exact amd64 Catalog visual passed locally.
- Bottleneck: exact GitHub run `30430189557` passed backend/frontend/secrets but browser job `90505399783` passed only 25/26. The Catalog visual remained 9,215 pixels different on all three attempts.
- Loop found: architecture pinning alone did not reproduce native GitHub font raster/layout under amd64-on-arm emulation. Current and previous GitHub actuals are byte-identical (`b816107d...`), while the local-emulated expected is `9627b4a7...`.
- Acceptance review initially classified the Buddhist-year wrapping difference as a layout blocker. Deeper current/prior/retry comparison proved the native CI actual byte-identical across runs; inspection found no overflow, clipping, overlap, or lost content. The reviewer explicitly amended the PR verdict at comment `5114373349`: native wrapping is accepted product behavior and no CSS change is required.
- Change applied: a visual baseline is not accepted from hashes or pixel counts alone. Inspect wrapping and downstream alignment first, require explicit reviewer classification, then source the Linux baseline from the exact native CI actual rather than local emulation. Conflicting reviewer statements must be reconciled on the PR before coding continues.
- Security review found the architecture gate could still pass direct CourseCatalog reads of `application_workflow_facts` and `document_publication_projections`. The negative fixture also omitted People, so a nonzero exit could come from the wrong missing-module diagnostic. Change: every introduced table receives an explicit module owner; negative fixtures contain the complete module set and assert the intended forbidden-access diagnostic.
- Evidence standard applied: browser expiry tests now control time rather than sleep; final runners compare current spec/CSS/snapshot hashes; native arm64 full behavior and native GitHub amd64 pixel truth are reported separately when local emulation crashes Chromium.
- Delivery result: one independent engineering gate merged, but Course Catalog did not meet the next-hour merge/release target. `localhost:8080` correctly remains on exact main `9749eaec`; no broken Catalog candidate was released.
- Next-hour target: replace only the Catalog Linux baseline from inspected native CI actual `b816107d`, pass exact GitHub browser 26/26, obtain fresh dual reciprocal approval, merge PR #34, release Catalog to `main` and `localhost:8080`, then rebase Issue #36 seed.

### 2026-07-29 15:07 +07

- Output: PR #34 passed CI 4/4 with Browser 26/26, dual reciprocal review, and merged at `e675535`; Issue #10 closed. PR #41 delivered the rebased PM docs at `40b7327`. PR #42 passed fresh PostgreSQL seed, readiness/smoke, real HTTP sign-in, CI 4/4, and dual reciprocal review; it merged at `a9389ac` and Issue #36 closed. PR #44 corrected the release dashboard and merged at `dda4177`.
- Parallel output: Issue #12 started from integration `e675535`. Backend contracts, migration, provider, routes, controller, and Thai UI exist without touching the seed-owned files. Phase evidence is posted at Issue comment `5114789252`.
- Visible runtime: `localhost:8080` remains intentionally pinned to exact `main` `9749eaec`. Course Catalog and the supported seed account are merged only to integration until release PR #43 passes on replacement exact head `dda4177`.
- Bottleneck: release validation is the critical path. Updating integration while PR #43 is open triggers a replacement head and duplicate exact-SHA CI runs; both backend/browser copies must settle before final review.
- Loop found: `progress.md` described PR #42 as pending, then PR #42 merged into the same release head. The release diff immediately became internally stale. Acceptance correctly blocked PR #43 before a false PM status reached `main`.
- Change applied: status claims now move to “delivered” only after authoritative merge/Issue refresh. The correction travelled through PR #44 with its own CI and dual reciprocal review. Hourly retro work uses a separate branch while PR #43 validates, avoiding another integration-head change.
- Review-process problem found: one PR #41 acceptance comment used unsafe shell quoting and lost every backticked exact literal. Security blocked the verdict because the SHA/run proof was unreadable.
- Change applied: reviewer comments must preserve literal SHA/run evidence with safe quoting and be rendered/checked before reciprocal approval.
- Local-runtime problem found after release: `.env` was absent while the named PostgreSQL volume still contained a cluster initialized with the previous password. `bin/bootstrap-env` generated a new password, so the first seed stopped before writes with `FATAL: password authentication failed for user "tapoda"`.
- Change applied: inspect the exact volume and stop only Tapoda containers before recovery. Because this release explicitly required a fresh local seed, remove only `tapoda-next_postgres-data`, state that the old local data is unrecoverable without backup, recreate it with the current `.env`, and rerun the reviewed seed. Future rebuilds must preserve `.env` with the named volume or perform this explicit reset instead of treating an authentication failure as an application defect.
- Delivery result: Catalog and seed implementation are integrated and inspectable, but the next-hour `main`/`:8080` release target is not complete. Quality gates found two documentation/process defects before release.
- Next-hour target: finish PR #43 exact `dda4177` CI and fresh dual reciprocal review, merge to `main`, rebuild `localhost:8080`, verify Catalog URLs plus seeded sign-in, then advance Issue #12 through focused/static/browser gates.

### 2026-07-29 16:07 +07

- Output: PR #43 passed duplicate exact-head CI runs 4/4, dual reciprocal review, and merged to `main` at `b3ff382`. `localhost:8080` now serves exact image `sha256:f4cf9c5cea8b340ca38d812dc65defdc6e5dbd3863e38f57100f1beaf6f1a6b7`; artifact labels, fresh guarded seed, readiness/smoke, Catalog URLs/content, seeded HTTP sign-in, and authenticated `/account` all passed. PR #45 published the runtime dashboard/retro/shared rule at integration merge `3444bfd`.
- Issue #12 output: the local Member Center candidate passed Pint 165 files, PHPStan 102/102, architecture, 27 affected backend tests / 286 assertions, all frontend gates, Browser 3/3, exact artifact verification, fresh seed, and seeded browser sign-in on isolated `:18012`. The candidate was committed cleanly and held before push because the canonical full PostgreSQL suite exposed a dependency failure.
- Bottleneck found: Course detail rendered the raw PostgreSQL session-formatted registration timestamp inside `<time datetime>`. The visible Thai date was correct, but the machine-readable value changed between UTC and Asia/Bangkok sessions. A global `PGTZ=Asia/Bangkok` made Course pass while breaking Identity expiry semantics.
- Change applied: split the cross-cutting defect into Issue #46 and PR #47 instead of hiding it inside Issue #12. The fix normalizes machine-readable HTML to ISO 8601 Asia/Bangkok, proves the stored instant under PostgreSQL `Etc/UTC`, keeps Thai/Buddhist text unchanged, and uses no `PGTZ`.
- Test-environment problem found: the isolated Compose suite inherited the root `.env` deterministic verification code, while tests submit `246810`. The first canonical run produced eight HTTP 422 verification failures unrelated to timezone behavior.
- Change applied: the canonical full-suite contract is UTC PostgreSQL plus explicit `IDENTITY_DETERMINISTIC_CODE=246810`; no timezone override. PR #47 then passed 83 tests / 572 assertions, CI 4/4, dual reciprocal review, and merged at `f27a0f0`; Issue #46 closed.
- Orchestration output: implementation-ready, dependency-pinned task packets were posted for Issues #13, #14, #15, and #16. Issue #17 preparation is active. These are read-only and define start conditions/conflict exclusions, so they reduce later learning cost without competing with Issue #12 files.
- Delivery result: the prior next-hour target was met for PR #43, `main`, and `localhost:8080`. Issue #12 reached a reviewed-gate-ready local candidate but was correctly delayed by a real integration contract defect; that blocker is now merged.
- Next-hour target: complete Issue #12 rebase on `f27a0f0`, pass the canonical full PostgreSQL suite 100%, push/open/review/merge its PR, release the Member Center to `main`/`:8080`, then start Issue #13 from the resulting exact integration SHA.

### 2026-07-29 17:07 +07 (recorded 17:17 +07)

- Checkpoint timing failure: this hourly snapshot was recorded 10 minutes late. The controller stayed inside PR/browser monitoring instead of interrupting at the fixed checkpoint. The missed deadline is a PM process defect, not hidden as an on-time update.
- Change applied: the fixed `HH:07` checkpoint takes priority over polling and gate monitoring. Record the authoritative snapshot first, even while tests continue, then resume. Prepare the retro branch before the checkpoint and do not depend on a CI result arriving first.
- Output: PR #47 passed CI 4/4 and dual reciprocal review, merged at `f27a0f0`, and closed Issue #46. Issue #12 rebased, passed the canonical PostgreSQL/Redis full suite, opened PR #48, and completed multiple exact-artifact browser runs. Implementation-ready read-only packets now cover Issues #13 through #24; Issue #25 preparation is active.
- Review output: the first PR #48 review posted four Mediums covering actionable Thai 422 states, existing-training edit UI, non-stub ApplicationWorkflow timeline, and mutation/audit atomicity. One batched replacement added transactional mutation seams, failure-injection rollback proofs, the owned timeline, and browser journeys. Subsequent review found dependent address-load failure handling, malformed-success schema handling, and a saved-address parent/child request race.
- Bottleneck: the Member Center's dependent reference-data state machine is the critical path. Each visible failure mode is small, but error, abort, retry, saved-value preservation, parent reset, stale child response, and schema validation interact. Patching one callback at a time creates replacement-SHA review loops.
- Change applied: treat address references as one fail-closed state machine. Validate the complete unknown response before state replacement, initialize saved parent then child sequentially, and invalidate/abort child generations on every parent start/failure/reset. Tests must cover malformed 200, failed request, abort, delayed stale child, retry, and full-chain recovery.
- Test-orchestration problem found: two targeted Playwright processes were launched in parallel against one stateful runtime. One assertion passed without a prompt process exit; the race test counted a request started by a post-save refresh before interception. Partial output was discarded.
- Change applied: Playwright processes that share a database/runtime run serially. Wait for prior real requests and route cleanup, reset fixtures after contamination, and require terminal exit codes. Parallelism remains for disjoint worktrees/runtimes only.
- Delivery result: the main Catalog/seed runtime remains healthy at exact `b3ff382`, but Issue #12 did not meet the merge/release target. Review found real product and test-state defects; the current PR head is invalid until the final address-state replacement passes serial exact-artifact gates.
- Next-hour target: finish serial malformed/race/browser and canonical backend gates, push one PR #48 replacement, obtain fresh dual reciprocal approval, merge/close Issue #12, release Member Center to `main`/`:8080`, and merge the accumulated PM retro/dashboard PR.

### 2026-07-29 17:42 +07 — Docker lifecycle incident

- Failure: agents created a new Compose project for isolation on repeated
  candidate and review runs. No owner tore those projects down. At least 18
  stale Tapoda Compose projects and more than 70 containers accumulated.
- Impact: host load reached `34.75`; `/signup` took 15.656 seconds and the
  verification request produced no response before the normal 30-second
  Playwright timeout. PostgreSQL recorded two abnormal untracked child exits,
  terminated all backends, and entered crash recovery. The app then observed
  `SQLSTATE[08006] ... database system is in recovery mode`.
- Exclusions: no PostgreSQL row blocker, Redis rate-limit lock, container
  restart, or OOM kill was present. After concurrent reset/probe activity
  stopped, the real journey returned to 331 ms GET, 1,131 ms verification
  request HTTP 202, and 876 ms verification HTTP 200.
- PM cause: isolation was optimized per test without a total runtime budget,
  teardown policy, or cleanup owner. The controller accepted new project names
  and monitored test output without checking Docker inventory.
- Correction applied: stopped 66 stale containers, then removed 18 stale
  Compose projects plus four stopped one-shot/standalone containers. Volumes
  were preserved. `tapoda-next` on `:8080` and
  `tapoda-issue12-browser` on `:18012` remain; unrelated legacy runtimes remain
  untouched.
- Permanent control: stable PostgreSQL/Redis/network/volume are reused.
  Candidate SHAs replace only the app image/container. The controller owns
  pre/post-gate inventory and cleanup. Direct PostgreSQL-container exec probes
  are prohibited; reset, seed, readiness, warmup, and browser work run
  serially.

### 2026-07-29 18:07 +07

- Checkpoint discipline: recorded at the fixed `18:07` boundary before
  resuming CI or agent monitoring. This corrects the late `17:07` process
  failure.
- Output: PR #48 replacement `e4ce03c` passed exact local frontend, Browser 6/6,
  backend 93 tests / 693 assertions, Pint, PHPStan, and architecture gates.
  GitHub backend/frontend/secrets passed, but the authoritative full browser
  job failed with 26 passed, 1 failed, and 5 not run. PR #49 PM documentation
  passed CI 4/4 and remains held behind #48.
- Review output: exact-SHA security review found two Mediums. Password negative
  flows discarded the useful Thai Identity message and lacked
  secret-redaction/session-invariance proof. Training creation lacked persisted
  idempotency, so an ambiguous committed response followed by retry could
  duplicate the training row and audit.
- Integration failure: the Member browser fixture was provisioned only when a
  local container environment variable existed. GitHub CI did not seed it, so
  the first test never found `member-bkk`; retries then reused the shared
  verification limiter and returned HTTP 429. The full configuration also ran
  state-sharing tests with two workers despite the serial-runtime rule.
- Change in progress: the password RED now passes with 1 test / 41 assertions.
  Sequential training replay/conflict/scope RED now passes with 1 test / 15
  assertions using a People-owned same-transaction idempotency claim. Remaining
  proof is real PostgreSQL concurrency, negative/ambiguous browser coverage,
  deterministic CI fixture provisioning, the configured-worker full browser
  suite, and all final gates.
- Task-sizing failure: #12 and the prepared #13 packet each combined multiple
  state machines and owners. Review of Issues #14–#32 found all 19 exceed the
  60–90 minute PR-sized rule; packets span 13,053–37,683 characters and often
  4–17 routes. Large task packets reduced ambiguity but did not reduce WIP or
  replacement-SHA blast radius.
- Change applied: #13 remains the acceptance umbrella and is split into child
  Issues #50–#54 for Form Engine, start/resume, autosave, atomic submit, and
  receipt/timeline. Future umbrella Issues are decomposed just in time before
  coding. Issue #30 is explicitly XL with six local child slices; Issue #31 is
  explicitly XL with five local rehearsal slices and excludes actual production
  cutover.
- Docker result: 18 stale Compose projects and four one-shot containers were
  removed without deleting volumes. Only `tapoda-next` and one current
  `tapoda-issue12-browser` candidate project remain for this rebuild. Host load
  recovered from `34.75` to `9.05`; current PostgreSQL/Redis services remain
  reused and healthy.
- Delivery result: #12 is not approved or ready to merge. Review and the full
  CI gate found real correctness/evidence defects after focused local success.
  No false release was made; `localhost:8080` remains the last approved main
  slice.
- Next-hour target: finish one #12 replacement batch, pass the configured full
  browser plus backend/static gates, push once, obtain CI 4/4 and fresh dual
  reciprocal exact-SHA review, merge #48, then release Member Center to
  `main`/`:8080` and activate child #50.

### 2026-07-29 19:07 +07

- Checkpoint discipline: recorded at the fixed boundary while the final exact
  browser gate continues. No CI or browser result was awaited before taking the
  snapshot.
- Output: #13 now has child Issues #50–#54. Every umbrella #14–#29 has a
  published PR-sized decomposition comment, and #30–#32 packets explicitly
  classify themselves as XL multi-PR local scopes. No later coding agent may
  receive the original umbrella as one batch.
- PR #48 output: clean one-commit SHA `4b4ecaa` passed GitHub CI 4/4 after the
  branch history was squashed. Its final tree matched the prior candidate
  exactly, but fresh reviews correctly rejected it despite green CI.
- Secret-gate lesson: the first gitleaks repair removed the synthetic
  password-like literal only from the final tree. Gitleaks scans every commit in
  the PR range, so the historical offending commit still failed. A safe
  branch-only squash from exact integration base, exact force-with-lease, local
  backup ref, tree-equivalence proof, and one clean commit removed it without
  disabling or allowlisting the detector.
- Review output: acceptance found a High retry defect. After an ambiguous
  committed training request, the visible `โหลดใหม่` action destroyed the
  React-ref-only idempotency key; retry after reload could duplicate the row and
  audit. Security added that every nonzero 5xx cleared the key and that an
  unkeyed SHA-256 of low-entropy encrypted training fields leaked equality and
  dictionary-test material.
- Change applied locally: pending training creation now persists only an opaque
  UUID in session storage; no course/provider/date payload is stored. Network
  failure, 5xx, payload edit, visible reload, and manual reload preserve the
  key. Confirmed 2xx/replay or explicit reconciled abandonment clears it.
  Browser proof covers commit+abort, reload, same-key HTTP 200 replay, one DOM
  row/one audit, 503 then changed-payload 409, and original-payload replay.
- Data-protection change: the unkeyed digest column is replaced by randomized
  authenticated encryption of canonical request JSON through the existing
  Laravel Encrypter. Replay decrypts and constant-compares under the same
  transaction; corruption fails closed. Equal payloads expose no deterministic
  database equality material and no plaintext field.
- Current exact local candidate: `e68d9dd852d80f0f841fc9be8ebd5b16e9ae5ad5`,
  image
  `sha256:a0b898b5b085987522b8221bde43723c8f9b99684aa4dd6deea57cda32a65195`.
  Exact seed/web/worker/scheduler assertions passed on reused
  `tapoda-issue12-browser` PostgreSQL/Redis/network/volume. Focused backend
  passed 1/27, PostgreSQL concurrency 1/16, full backend 96/709, service
  integration 9/68, Member browser 6/6, Pint 166, PHPStan 105, architecture,
  Composer/npm audit, and all frontend gates. Final exact browser reruns are in
  progress; nothing is pushed.
- Docker result: no new project was created. The candidate reused the same
  four-hour PostgreSQL/Redis services and replaced only exact app containers.
  Host load is `6.22`, down from the incident peak `34.75`.
- PM result: PR #49 head `d312eaf` passed CI 4/4 and remains held behind #48.
  `localhost:8080` remains approved main `b3ff382`; no rejected candidate was
  released.
- Delivery result: #12 still misses the merge/release target, but the hour
  converted two review findings into executable cross-reload and encrypted
  idempotency contracts, completed task decomposition for every remaining
  umbrella, and reached full local gates except the terminal browser reruns.
- Next-hour target: finish exact Member/full browser gates, push one replacement,
  obtain CI 4/4 and fresh reciprocal security/acceptance approval, merge #48,
  release Member Center to `main`/`:8080`, merge/rebase PM evidence, and activate
  child #50 only after the resulting exact integration SHA exists.
