# Prove shadow parity and complete staff UAT

Status: `ready-for-agent`

## What to build

Run the migrated cohort in shadow mode and prove that approved behavior, data, outputs, communications, compatibility links, and check-in paths match the signed contracts. Resolve every unexplained difference before staff acceptance.

## Acceptance criteria

- [ ] Counts and checksums reconcile by course, center, person, category, lifecycle state, teacher, invitation, review, check-in, and operations facts.
- [ ] Every approved persona and conditional-form fixture replays successfully against migrated versions and immutable submissions.
- [ ] Applicant, teacher, laundry, print, and eight-sheet outputs match their golden fixtures or approved intentional differences.
- [ ] Email audience, template, attachment, map, link, retry, and delivery-state matrices pass without duplicate production sends.
- [ ] Active legacy routes, action links, passwords, documents, redirects, and historical reads resolve through approved compatibility behavior.
- [ ] Manual and card-assisted check-in pass supported workstation, mismatch, offline, timeout, and fallback scenarios.
- [ ] Security, privacy, accessibility, responsive, performance, backup-restore, and failed-job exercises pass on production-shape data.
- [ ] Every difference has a disposition; critical unexplained differences are zero.
- [ ] Operations, product, privacy, and delivery owners sign staff UAT acceptance.

## Blocked by

- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [25 Check in through the secured Thai ID companion](./25-check-in-through-the-secured-thai-id-companion.md)
- [27 View and export the eight applicant report groups](./27-view-and-export-the-eight-applicant-report-groups.md)
- [28 Review and print the five teacher report groups](./28-review-and-print-the-five-teacher-report-groups.md)
- [29 Migrate one complete course cohort idempotently](./29-migrate-one-complete-course-cohort-idempotently.md)
