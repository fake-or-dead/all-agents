# Operate one course session from Course Workspace

Status: `ready-for-agent`

## What to build

Give an authorized course manager one course-session workspace. Configure dates, center, teachers, policy, capacity, documents, registration window, and check-in access; inspect accurate lifecycle counts and application projections; navigate to review, invitations, participants, communications, reports, documents, audit, and settings without creating another state owner.

## Acceptance criteria

- [ ] Course definitions and sessions use explicit centers, teachers, dates, registration policies, category rules, capacity rules, documents, and operational settings.
- [ ] Public availability changes only through authorized policy commands and is reflected by the public course journey.
- [ ] Workspace access is scoped to the actor's approved center and course session.
- [ ] Overview and participant counts come from published lifecycle and classification projections instead of hard-coded or page-specific status queries.
- [ ] Applications, Review, Invitations, Participants, Check-in, Operations, Communications, Reports, Documents, Audit, and Settings compose owning-module projections.
- [ ] The workspace owns no application, review, invitation, attendance, notification, or report state.
- [ ] Thai-first desktop and mobile navigation, loading, empty, denied, stale, failure, and success states pass.
- [ ] `FLOW-ADMIN-01` and the single-record portion of `FLOW-COURSE-01` pass.

## Blocked by

- [09 Bootstrap one deployable audited platform path](./09-bootstrap-one-deployable-audited-platform-path.md)
- [10 Browse and inspect an eligible course session](./10-browse-and-inspect-an-eligible-course-session.md)
- [17 Review one immutable submission and record a decision](./17-review-one-immutable-submission-and-record-a-decision.md)
- [18 Invite and confirm one selected applicant](./18-invite-and-confirm-one-selected-applicant.md)
- [19 Complete alumni/staff responses, decline, cancel, and withdrawal](./19-complete-alumni-staff-response-decline-cancel-and-withdrawal.md)
