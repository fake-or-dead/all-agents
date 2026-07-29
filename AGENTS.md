## Agent skills

### Issue tracker

Issues live in `https://github.com/fake-or-dead/all-agents/issues`. The canonical PRD remains `docs/product/tapoda-rebuild-prd.md`. Do not create or update `.scratch/` issue files. See `docs/agents/issue-tracker.md`.

### Triage labels

Uses `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout using root `CONTEXT.md` and `docs/adr/`. See `docs/agents/domain.md`.

### Agent delivery

Issue branches target `integration/local-complete`. Each issue requires two independent, cross-reviewed approvals on the exact head SHA. See `docs/agents/review-protocol.md`.

### Local-only runtime

Develop, run, and test against local Docker services only. Use local PostgreSQL, local Redis, local storage, and deterministic fake provider adapters. Never connect the application or tests to production databases, third-party provider APIs, remote object storage, or other external application services. Keep `http://localhost:8080` available for user self-checks.
