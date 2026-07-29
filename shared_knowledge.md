# Shared Delivery Knowledge

Owner: PM/controller. Coding and review agents must read this file before
starting a task. Update it when a repeated mistake, architecture rule, test
pattern, or runtime constraint is discovered. Do not use it for transient
progress; use `progress.md` for that and `retrospective.md` for learning history.

## Source-of-truth hierarchy

1. Approved PRD: `docs/product/tapoda-rebuild-prd.md`.
2. GitHub Issue acceptance criteria and dependency links.
3. Merged architecture decisions and module ownership.
4. `shared_knowledge.md` delivery standards.
5. Tests and current implementation.

GitHub Issues #1–#32 are canonical. Never recreate task files under `.scratch/`.

## Standard task packet

Every coding-agent assignment includes:

- issue and exact base SHA;
- owned module/files and known conflict set;
- acceptance criteria and excluded production behavior;
- required local services and exact runtime port;
- focused, affected-suite, static, browser, and artifact gates;
- issue/PR update requirements;
- push authority and whether history rewrite is allowed.

Before editing, the agent reads:

1. the GitHub Issue and comments;
2. `progress.md`;
3. this file;
4. affected architecture docs and tests;
5. current branch status and diff.

## Agent progress contract

The assigned agent posts its own GitHub Issue progress comment at:

1. start: agent, branch, base SHA, plan, dependencies;
2. first executable checkpoint: RED test or inspected baseline;
3. blocker or concern: evidence and proposed correction;
4. local candidate: exact SHA and complete gate results;
5. pushed candidate: PR URL, exact remote SHA, CI state;
6. review fix: concern URL, replacement SHA, verification;
7. completion: merge SHA, runtime evidence, remaining exclusions.

Behavior concerns go on the PR before code changes. Delivery progress goes on the
Issue. The PM closes Issues and merges PRs only after evidence is complete.

## PM state refresh contract

Before committing or presenting `progress.md`, the PM refreshes:

- GitHub Issue open/closed counts and active issue comments;
- every active PR head SHA, CI result, review state, and mergeability;
- active agent state and exact local candidate SHA;
- user-visible and candidate Docker runtime readiness.

Record concrete failing run/job IDs and the next owner/gate. Never copy a
previous status under a new timestamp.

## Release and review standard

- Branches target `integration/local-complete` while a slice is under
  development.
- A completed independently usable slice is released from integration to
  `main`; do not wait for all 32 scopes.
- Release cadence target: 60–90 minutes when a slice is ready.
- Every candidate is immutable by SHA.
- Two independent reviewers cover security/architecture and
  product/acceptance.
- Reviewers cross-review each other's verdicts.
- Any new commit invalidates all previous approvals.
- Every concern is commented before the coding agent fixes it.
- Merge only with exact-head CI green and both reciprocal approvals.

## Local-only runtime standard

- Application services: local Docker PostgreSQL, Redis, local storage, queue,
  scheduler, and deterministic fake adapters.
- Never connect application/tests to production databases, provider APIs,
  remote object storage, or other external application services.
- GitHub is allowed only for source, Issues, PRs, reviews, and CI.
- Keep `http://localhost:8080` for the user-visible integrated build.
- Candidate runtimes use isolated project names and ports.
- Render Compose config before startup. Port-list overrides must use
  `!override`; ordinary lists merge and may retain `8080:8080`.
- Assert one exact image digest/revision across migrate or seed, web, worker,
  and scheduler.
- Fresh scheduler readiness may remain 503 until the first minute-boundary
  heartbeat. Inspect logs through one interval before diagnosing failure.

## Architecture ownership

- IdentityAccess owns canonical Accounts, credentials, sessions, verification,
  recovery, and account status.
- People owns canonical people, identifiers, and person-account link proofs.
- DocumentsConsent owns immutable consent documents, versions, and acceptance.
- CourseCatalog reads canonical active Account/People ownership through a
  narrow IdentityAccess-owned port. It must not create projection/cache copies
  that can become stale.
- Account deactivation must affect authorization immediately, even when an
  authenticated model instance is stale.
- Architecture checks include negative cross-module fixtures for owned tables.

## Security patterns

- Lookup-key rotation requires current and previous version/key pairs to be
  either both absent or both present; versions must differ; keys must map under
  their declared version and remain domain-separated.
- Rate limits use privacy-safe per-bucket Redis locks acquired in deterministic
  sorted order and released in reverse order. Overlapping buckets serialize;
  unrelated subjects proceed independently.
- Tests for Redis concurrency must explicitly use shared Compose Redis. The
  PHPUnit array cache cannot prove cross-process atomicity.
- Recovery-token responses use `Referrer-Policy: no-referrer` and
  `Cache-Control: no-store` through deployed Caddy behavior.
- Deterministic seed credentials are allowed only when environment is
  `local|testing` and the verification adapter is exactly
  `deterministic-fake`; guard before the first write.
- Production approvals, writes, cutover, and destructive retirement remain
  excluded.

## Test and gate patterns

- Long-running Playwright gates run from a root-owned persistent execution
  session and require the final exit code. Partial `13/16` or `15/16` output is
  not evidence.
- Bind Playwright to an explicit candidate URL and recovery container.
- Do not reuse partially exercised Redis rate-limit state as a clean full-suite
  environment.
- Build or mount the exact current candidate into the browser runner. Before a
  final run, compare hashes for changed browser specs, CSS, and snapshots
  inside the runner against the worktree; an older container context invalidates
  the result.
- Pin the reviewed browser architecture. Native arm64 glyph raster output is
  not evidence for a Linux amd64 CI baseline.
- Time-boundary tests must control time and response release explicitly. Hold
  an in-flight request, fast-forward the browser clock past expiry, then release
  it; short wall-clock sleeps make correctness depend on runner speed.
- Keyboard-only acceptance uses real Tab/Shift+Tab/Space/Enter navigation and
  asserts every focus transition; direct locator focus/click is not evidence.
- Full-document Axe checks include shared header/navigation states.
- Visual baseline refresh requires a pixel-level cause inspection; never update
  snapshots solely to make the gate green.
- PHPStan runs through the repository-owned command from issue #37 with an
  explicit reviewed memory limit; do not lower rules or paths.
- `migrate:fresh --seed --force` is destructive only to the isolated local
  Compose database and must use the guarded local fixture.

## Definition of Done

A scope is done only when:

- acceptance and architecture ownership are implemented;
- focused, affected-suite, real-service, static, browser, and exact-artifact
  gates appropriate to the scope pass;
- exact remote SHA has CI green;
- both reviewers approve and reciprocally cross-review that SHA;
- PR is merged and Issue is closed with evidence;
- integrated `localhost:8080` is rebuilt for a user-visible vertical slice;
- `progress.md`, `retrospective.md`, and this file are updated when applicable.
