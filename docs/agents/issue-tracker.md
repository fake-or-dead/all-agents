# Issue tracker: GitHub Issues

Repository: `fake-or-dead/all-agents`

Canonical tracker: <https://github.com/fake-or-dead/all-agents/issues>

The canonical PRD remains [`docs/product/tapoda-rebuild-prd.md`](../product/tapoda-rebuild-prd.md). Do not create or update `.scratch/` issue files.

## Conventions

- One independently grabbable vertical slice per GitHub Issue.
- Titles retain the stable plan prefix: `[01]` through `[32]`.
- Bodies contain `What to build`, `Acceptance criteria`, `Blocked by`, and `Agent tracking`.
- Dependencies reference real GitHub issue numbers.
- Use labels from [`triage-labels.md`](triage-labels.md).
- Active agents update the issue body or add a progress comment with agent name, branch, exact SHA, checks, blockers, and review state.
- The assigned agent posts phase-boundary comments itself: start, first
  executable checkpoint, blocker, local candidate, pushed candidate, review
  fix, and completion.
- Completed issues record both reviewer approvals against the same exact SHA before closure.

## Publishing and fetching

- Publish with `gh issue create -R fake-or-dead/all-agents`.
- Fetch with `gh issue view <number> -R fake-or-dead/all-agents --comments`.
- List active work with `gh issue list -R fake-or-dead/all-agents --state open`.
