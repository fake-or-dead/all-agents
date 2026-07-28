# Complete alumni/staff responses, decline, cancel, and withdrawal

Status: `ready-for-agent`

## What to build

Extend invitation response and post-invitation confirmation across alumni and staff-applicant personas. Add applicant-authored decline, cancellation before confirmation, and withdrawal after confirmation while keeping every outcome distinct from reviewer rejection.

## Acceptance criteria

- [ ] `post-new` and `post-alumni` stage order and every approved staff/persona branch use the same Form Engine and Application Workflow interfaces.
- [ ] Alumni confirmation freezes training history and all applicable typed details into the exact superseding submission.
- [ ] Applicant decline records an applicant-authored reason and a distinct lifecycle outcome from reviewer rejection.
- [ ] Cancellation before confirmation and withdrawal after confirmation enforce separate transitions, reasons, receipts, and notification policies.
- [ ] All public actions use preview-before-POST, one-use tokens, ownership/state validation, idempotency, audit, and safe expired/stale recovery.
- [ ] Legacy labels remain display snapshots but cannot collapse distinct canonical outcomes.
- [ ] `FLOW-APP-04`, `FLOW-APP-05`, and all invitation outcome fixtures pass.

## Blocked by

- [16 Complete staff-applicant and conditional form branches](./16-complete-staff-applicant-and-conditional-form-branches.md)
- [18 Invite and confirm one selected applicant](./18-invite-and-confirm-one-selected-applicant.md)
