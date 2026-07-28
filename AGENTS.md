## Agent skills

### Issue tracker

Issues and PRDs use local Markdown under `.scratch/`. External PRs are not a triage surface. See `docs/agents/issue-tracker.md`.

### Triage labels

Uses `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, and `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout using root `CONTEXT.md` and `docs/adr/`. See `docs/agents/domain.md`.

### Agent delivery

Issue branches target `integration/local-complete`. Each issue requires two independent, cross-reviewed approvals on the exact head SHA. See `docs/agents/review-protocol.md`.
