## Agent skills

### Issue tracker

Issues live in `https://github.com/fake-or-dead/all-agents/issues`. The canonical PRD remains `docs/product/tapoda-rebuild-prd.md`. Do not create or update `.scratch/` issue files. See `docs/agents/issue-tracker.md`.

### Triage labels

Uses `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout using root `CONTEXT.md` and `docs/adr/`. See `docs/agents/domain.md`.

### Agent delivery

Issue branches target `integration/local-complete`. Each issue requires two independent, cross-reviewed approvals on the exact head SHA. See `docs/agents/review-protocol.md`.

Before starting work, every coding and review agent reads `progress.md` and
`shared_knowledge.md`. The assigned coding agent posts its own phase-boundary
updates to the GitHub Issue. The PM/controller owns `progress.md`,
`retrospective.md`, release cadence, merge decisions, and user-facing status.

### Local-only runtime

Develop, run, and test against local Docker services only. Use local PostgreSQL, local Redis, local storage, and deterministic fake provider adapters. Never connect the application or tests to production databases, third-party provider APIs, remote object storage, or other external application services. Keep `http://localhost:8080` available for user self-checks.

### PM operating system

- `progress.md`: current presentation-ready delivery status, owners,
  bottlenecks, release state, and forecast.
- `retrospective.md`: hourly output, problems, root causes, process changes, and
  next measurable target.
- `shared_knowledge.md`: stable architecture, runtime, security, test, review,
  and agent-task standards.

Update the retrospective every fixed active-delivery hour without waiting for a
user prompt. Release independently usable reviewed slices to `main`; do not wait
for all 32 scopes.
