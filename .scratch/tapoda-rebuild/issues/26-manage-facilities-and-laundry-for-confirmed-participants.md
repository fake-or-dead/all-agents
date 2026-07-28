# Manage facilities and laundry for confirmed participants

Status: `ready-for-agent`

## What to build

Let authorized operations staff manage room assignments, facility requests, daily laundry, purchases, and cost adjustments for confirmed participants. Show and export the same course-scoped facts and totals from one operations projection.

## Acceptance criteria

- [ ] Room assignments, facility requests, participant services, purchases, and signed cost adjustments retain actor, reason, time, validity, and audit data.
- [ ] Laundry supports approved category segmentation, days `01`–`08`, quantity, unit cost, purchase cost, adjustments, and total.
- [ ] Dinner, seating, accommodation, and other sensitive needs follow approved field-level permissions.
- [ ] Screen, mobile card, print, and XLSX consume one membership and calculation projection.
- [ ] Original service and purchase rows remain immutable when corrected through adjustments.
- [ ] Course/session scope, authorized updates, downloads, partial failures, concurrency, Thai text, ordering, and totals are tested.
- [ ] `FLOW-OPS-01` and the approved laundry golden fixture pass exactly.

## Blocked by

- [19 Complete alumni/staff responses, decline, cancel, and withdrawal](./19-complete-alumni-staff-response-decline-cancel-and-withdrawal.md)
- [20 Operate one course session from Course Workspace](./20-operate-one-course-session-from-course-workspace.md)
- [24 Check in a participant manually](./24-check-in-a-participant-manually.md)
