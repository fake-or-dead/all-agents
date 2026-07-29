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
- Next-hour target: integrate both PR #35 commits, pass focused and full local gates, and produce one replacement SHA ready for dual exact-SHA review.
