# Invite and confirm one selected applicant

Status: `ready-for-agent`

## What to build

Turn one approved review decision into a durable invitation for a new-person applicant. Deliver the correct notification through an outbox, let the applicant preview and redeem a secure one-use action, complete required post-invitation information, confirm attendance, and see a truthful receipt.

## Acceptance criteria

- [ ] Invitation creation records audience, course session, decision, response state, expiry, and an idempotency key without depending on provider success.
- [ ] Notification intent, template version, recipient, links, attachments, provider message ID, attempts, retry, delivery, bounce, and failure remain observable.
- [ ] GET action links only preview; confirmation requires an authorized POST using a hashed, expiring, one-use token.
- [ ] The new-person post-invitation form uses the shared Form Engine and freezes a superseding immutable submission.
- [ ] Confirmation uses Application Workflow for the allowed transition and writes status, audit, response, token redemption, and outbox facts atomically.
- [ ] Replayed, expired, tampered, stale, duplicate, delivery-failed, and concurrent actions return safe recovery outcomes.
- [ ] The applicant receipt shows confirmed state, exact submission, time, next task, communication state, and cancellation route.
- [ ] `FLOW-INV-02`, `FLOW-NOTIFY-01`, and the new-person portion of `FLOW-APP-04` pass.

## Blocked by

- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [11 Register, verify, sign in, recover, and secure an account](./11-register-verify-sign-in-recover-and-secure-an-account.md)
- [13 Submit an initial new-person application](./13-submit-an-initial-new-person-application.md)
- [17 Review one immutable submission and record a decision](./17-review-one-immutable-submission-and-record-a-decision.md)
