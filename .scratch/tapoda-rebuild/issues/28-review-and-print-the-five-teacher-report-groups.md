# Review and print the five teacher report groups

Status: `ready-for-agent`

## What to build

Give an authorized teacher reviewer one versioned projection for five participant groups, group counters, accessible participant detail, and selection of up to ten approved participant sheets for print.

## Acceptance criteria

- [ ] One classification policy produces all five group counters, list rows, participant details, and print selection.
- [ ] The projection reads the exact immutable submission and approved current attendance facts with stable Thai labels and ordering.
- [ ] Emergency contact, health, medication, attendance, dinner, seating, and special-request fields follow the teacher field policy.
- [ ] The detail dialog owns focus, labeling, Escape, close rules, scroll lock, and return focus.
- [ ] Selection behavior for 0, 1, 10, and 11 participants matches the approved maximum-ten rule.
- [ ] Print output preserves approved page size, page breaks, field grouping, labels, and participant order through the shared print adapter.
- [ ] Screen, detail, and print pass golden fixtures and remain historically stable after profile or form edits.
- [ ] `FLOW-REPORT-02`, authorization, accessibility, and print tests pass.

## Blocked by

- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [17 Review one immutable submission and record a decision](./17-review-one-immutable-submission-and-record-a-decision.md)
- [20 Operate one course session from Course Workspace](./20-operate-one-course-session-from-course-workspace.md)
- [24 Check in a participant manually](./24-check-in-a-participant-manually.md)
