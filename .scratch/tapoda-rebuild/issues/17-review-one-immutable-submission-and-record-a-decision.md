# Review one immutable submission and record a decision

Status: `ready-for-agent`

## What to build

Let an authorized reviewer receive one submitted application, inspect the exact immutable evidence, save a private review draft, submit an immutable review, and record a separate course-session decision that requests an authorized application transition.

## Acceptance criteria

- [ ] Review queues use approved course, category, alumni, monastic, staff-intent, willingness, assignment, conflict, and aging rules.
- [ ] A review round references one exact application submission and cannot read mutable profile, training, form-label, or answer data as historical evidence.
- [ ] Reviewer assignment, sensitive field visibility, due time, conflict state, and course/session scope are enforced.
- [ ] Private review drafts use optimistic locking; submission freezes the review, reason codes, approved notes, private-note classification, and scores where allowed.
- [ ] A decision references its round and contributing reviews while remaining separate from application lifecycle state.
- [ ] Decision publication asks Application Workflow for an allowed transition and records audit and outbox events atomically.
- [ ] Corrections supersede reviews; reconsideration opens a new round; course A and course B history never overwrite each other.
- [ ] `FLOW-REVIEW-01`, concurrency, authorization, projection replay, and historical-stability tests pass.

## Blocked by

- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [13 Submit an initial new-person application](./13-submit-an-initial-new-person-application.md)
