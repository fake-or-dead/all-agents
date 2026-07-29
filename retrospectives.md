# Retrospectives

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
