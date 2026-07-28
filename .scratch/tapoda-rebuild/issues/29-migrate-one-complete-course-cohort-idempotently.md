# Migrate one complete course cohort idempotently

Status: `ready-for-agent`

## What to build

Migrate one representative course cohort end to end through every target owner: reference data, accounts and people, course session, documents, form versions, applications, drafts, submissions, answers, training, review, invitation, check-in, operations, consent, notifications, exports, and audits. Preserve provenance and quarantine contradictions without dual-writing.

## Acceptance criteria

- [ ] The migration runs in the approved dependency order and can resume or rerun without duplicate facts or changed immutable records.
- [ ] Every destination record retains source system, source table, legacy identifier, migration batch, and approved provenance/confidence.
- [ ] Ambiguous, invalid, orphaned, or contradictory records enter quarantine with reason, evidence, severity, and owner.
- [ ] Legacy statuses, alumni eligibility, staff intent/assignment, profile, training summaries, answers, labels, reviews, invitations, and consent are mapped without manufactured history.
- [ ] Shared aggregates keep exactly one writer and follow the approved coexistence read/write matrix.
- [ ] Counts, checksums, current projections, status, review outcome, profile snapshot, answer receipt, training summary, and output membership reconcile for the cohort.
- [ ] Failed batches roll back or restart safely and expose observable progress and operator diagnostics without PII leakage.
- [ ] Migration, quarantine, reconciliation, and rollback tests use anonymized production-shape fixtures.

## Blocked by

- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [10 Browse and inspect an eligible course session](./10-browse-and-inspect-an-eligible-course-session.md)
- [11 Register, verify, sign in, recover, and secure an account](./11-register-verify-sign-in-recover-and-secure-an-account.md)
- [12 Manage profile, training history, applications, and security](./12-manage-profile-training-history-applications-and-security.md)
- [13 Submit an initial new-person application](./13-submit-an-initial-new-person-application.md)
- [14 Publish an immutable application form version](./14-publish-an-immutable-application-form-version.md)
- [15 Submit an alumni application with preserved eligibility](./15-submit-an-alumni-application-with-preserved-eligibility.md)
- [16 Complete staff-applicant and conditional form branches](./16-complete-staff-applicant-and-conditional-form-branches.md)
- [17 Review one immutable submission and record a decision](./17-review-one-immutable-submission-and-record-a-decision.md)
- [18 Invite and confirm one selected applicant](./18-invite-and-confirm-one-selected-applicant.md)
- [19 Complete alumni/staff responses, decline, cancel, and withdrawal](./19-complete-alumni-staff-response-decline-cancel-and-withdrawal.md)
- [20 Operate one course session from Course Workspace](./20-operate-one-course-session-from-course-workspace.md)
- [21 Run bulk transitions, reminders, and cross-course work queues](./21-run-bulk-transitions-reminders-and-cross-course-work-queues.md)
- [22 Administer scoped staff accounts safely](./22-administer-scoped-staff-accounts-safely.md)
- [23 Exchange legacy routes, payloads, passwords, and action links](./23-exchange-legacy-routes-payloads-passwords-and-action-links.md)
- [24 Check in a participant manually](./24-check-in-a-participant-manually.md)
- [25 Check in through the secured Thai ID companion](./25-check-in-through-the-secured-thai-id-companion.md)
- [26 Manage facilities and laundry for confirmed participants](./26-manage-facilities-and-laundry-for-confirmed-participants.md)
- [27 View and export the eight applicant report groups](./27-view-and-export-the-eight-applicant-report-groups.md)
- [28 Review and print the five teacher report groups](./28-review-and-print-the-five-teacher-report-groups.md)
