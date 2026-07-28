# View and export the eight applicant report groups

Status: `ready-for-agent`

## What to build

Give an authorized reporting user one versioned applicant report specification that feeds the eight screen groups and queued eight-sheet workbook. Preserve approved grouping, fields, ordering, Thai formatting, and sticky identity behavior while enforcing field permissions, export audit, and artifact retention.

## Acceptance criteria

- [ ] One approved classification policy produces all eight group counters, screen rows, worksheet rows, and ordering.
- [ ] Report data comes from stable module projections and immutable submissions rather than arbitrary cross-module table joins.
- [ ] Identity, lifecycle, dates, health, medication, substance-use, training, attendance, confirmation, check-in, and completion fields follow the approved field policy.
- [ ] The accessible data grid supports pinned identity columns, named horizontal scrolling, keyboard use, column visibility, mobile-equivalent cards, and no color-only status.
- [ ] Queued XLSX generation exposes progress, retry, partial/failed state, artifact checksum, expiry, authorized download, and audit.
- [ ] Spreadsheet values neutralize formula injection and preserve approved Thai/Buddhist date and label behavior.
- [ ] Screen and workbook pass the group-by-group golden fixture, including documented intentional differences.
- [ ] `FLOW-REPORT-01`, large-volume, authorization, failure, accessibility, and print/export tests pass.

## Blocked by

- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [17 Review one immutable submission and record a decision](./17-review-one-immutable-submission-and-record-a-decision.md)
- [18 Invite and confirm one selected applicant](./18-invite-and-confirm-one-selected-applicant.md)
- [19 Complete alumni/staff responses, decline, cancel, and withdrawal](./19-complete-alumni-staff-response-decline-cancel-and-withdrawal.md)
- [20 Operate one course session from Course Workspace](./20-operate-one-course-session-from-course-workspace.md)
- [21 Run bulk transitions, reminders, and cross-course work queues](./21-run-bulk-transitions-reminders-and-cross-course-work-queues.md)
- [24 Check in a participant manually](./24-check-in-a-participant-manually.md)
- [26 Manage facilities and laundry for confirmed participants](./26-manage-facilities-and-laundry-for-confirmed-participants.md)
