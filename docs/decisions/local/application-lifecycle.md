# Local application lifecycle contract

**Status:** local implementation decision. **Production-owner approval is excluded.**

This contract implements G2 locally from the PRD, module blueprint, flow ledger, and legacy source at `3d2c3a4`. A later production-truth decision may supersede a legacy mapping, but code must use this contract now rather than legacy labels.

## Ownership and invariants

- `ApplicationWorkflow` is the only application-state writer. It appends an immutable status event and updates the current-state projection in one transaction.
- Every status event contains application ID, previous state, new state, actor identity/type, source, reason code, occurred-at UTC, workflow version, correlation ID, idempotency key, and causation ID where applicable.
- A submitted review and a review decision are immutable records owned by Review & Selection. A decision requests a transition; it is not an application status.
- Notifications are requested through the committed outbox. Delivery state never changes lifecycle state.
- A correction supersedes an immutable submission/review/decision; it never edits history.

## Canonical states and transitions

| From | Command / actor | Prerequisites | To | Required reason / effect |
|---|---|---|---|---|
| — | applicant starts | authorized person, registration policy admits start | `draft` | `application_started`; one active application per person/session/intent unless approved exception |
| `draft` | applicant submits initial form | current draft version; applicable fields valid; required consent accepted | `submitted` | `initial_submission`; freeze submission/profile/typed records/answers/consent |
| `submitted` | reviewer or course manager opens round | exact submission, scoped authorization | `under_review` | `review_started`; create/reopen review round only |
| `under_review` | decision `select` | immutable decision on the round; invitation policy valid | `invited` | decision reason; issue invitation/action token and outbox request |
| `under_review` | decision `do_not_select` | immutable decision on the round | `rejected` | reviewer-decision reason required; no invitation |
| `invited` | applicant accepts confirmation | valid one-use action token; post-invitation draft valid; confirmation commitments accepted | `confirmed` | `invitation_accepted`; freeze superseding submission and request confirmation notification |
| `invited` | applicant declines | valid one-use action token | `declined_invitation` | applicant decline reason optional; distinct from `rejected` |
| `invited` | applicant cancels before confirmation | valid authenticated/action-token command | `cancelled_before_confirmation` | cancellation reason required |
| `confirmed` | check-in operator checks in | course/session scope; confirmed participant; idempotent attendance command | `checked_in` | arrival evidence/source required |
| `confirmed` | applicant or authorized operations actor withdraws | policy window and authorization | `withdrawn_after_confirmation` | withdrawal reason required |
| `confirmed` | course closes attendance | no recorded check-in; authorized operations actor | `no_show` | closure batch/correlation and reason required |
| `checked_in` | authorized operations actor completes cohort | participation-finalization evidence | `completed` | completion reason; create/verify alumni eligibility |

Terminal states are `rejected`, `declined_invitation`, `cancelled_before_confirmation`, `withdrawn_after_confirmation`, `no_show`, and `completed`. No terminal state transitions without an approved correction/reconsideration command that creates a new application or superseding decision; it must not rewrite the terminal event.

`submitted → under_review` is optional operationally: a review round can be created immediately after submission, but the derived projection must show `under_review` once it exists. `confirmed → checked_in` and `checked_in → completed` are never implied by time alone.

## Review decisions: separate facts

`ReviewDecision` has `round_id`, exact `application_submission_id`, decision key (`select` or `do_not_select`), reason code, actor, time, contributing immutable reviews, supersedes ID, and correlation ID.

- `select` requests `under_review → invited`; `do_not_select` requests `under_review → rejected`.
- A reviewer cannot directly set application status. A course manager may record the decision only within assigned course-session scope.
- Reconsideration creates a new review round against a named submission. Correction creates a superseding decision event. Neither alters a prior decision or status event.
- Legacy rows lacking reviewer, score, note, or rationale import those fields as `legacy_unknown`; never fabricate provenance.

## Alumni eligibility

An `AlumniEligibilityEvent` is separate from application state and has person, source application/submission, eligibility key, rule version, source fact, migration batch where relevant, actor/system, time, and revocation/supersession reference.

- New rule: only `checked_in → completed` creates or verifies `completed_course` eligibility.
- Local legacy-preservation rule: an existing legacy-guided alumni assignment is imported as `legacy_preserved` if a prior application has legacy status `approved`, `confirmed`, `checkin`, or `finalize`. Preserve the raw source status and source application where known.
- A `legacy_preserved` record selects alumni flow until a later approved recalculation policy supersedes it. Do not silently demote a person to new-person flow.
- A current `contact.is_alumni` flag without a source application imports as `legacy_flag_unproven`, remains eligible locally, and is queued for reconciliation; it is not evidence of completed attendance.

## Staff applicant and course staff

`staff_applicant` is an application intent selected at application start from the authorized course-session policy. It is neither a role nor an assignment.

- `course_staff` is a separately created, course-session-scoped assignment with position, start/end validity, assigner, reason, and audit event.
- Selection/invitation/confirmation of a staff applicant does not create course-staff permissions or an assignment.
- Only an authorized assignment command creates course-staff access. Removing/expiring an assignment revokes that access without changing the application intent or historical submission.
- Trainee and staff-applicant use the same lifecycle. Their forms, queues, invitation templates, and eligibility can differ only through server-derived context and published rules.

## Legacy import matrix

| Legacy value | Local target | Evidence/rule | Quarantine condition |
|---|---|---|---|
| `draft` | `draft` | direct | none |
| `applying` | `draft` | preserve last completed legacy step | missing person/session |
| `applicant_pending` | `submitted` | only with submission/apply-date evidence | otherwise `legacy_state_unresolved` |
| `applied` | `submitted` | direct | missing person/session |
| `approved` | `invited` | legacy approval controller stores invitation behavior | no application/session |
| `invited` | `invited` | direct | none |
| `accepted` | `legacy_state_unresolved` | no local semantic inference | always until record-level evidence maps to `confirmed` |
| `confirmed` | `confirmed` | direct | none |
| `checkin` | `checked_in` | direct | none |
| `finalize` | `completed` | direct; create completion eligibility | none |
| `rejected` | `rejected` | reviewer/administrative actor evidence | applicant decline evidence maps to `declined_invitation`; neither evidence quarantines |
| `canceled` | `cancelled_before_confirmation` | no confirmation evidence | confirmation evidence maps to `withdrawn_after_confirmation`; conflicting/missing time quarantines |
| `left` | `withdrawn_after_confirmation` | explicit operational evidence only | otherwise `legacy_state_unresolved` |

Quarantine is an import record, not an invented lifecycle state. It stores raw value, source row reference, missing/conflicting evidence, migration batch, and a resolution command. Quarantined rows never obtain automatic invitations, alumni completion eligibility, or staff permissions.

## Local completion boundary

This is decision-complete for local implementation and fixtures. It does not assert production data truth, production cron behavior, legal retention, or production-owner approval.

## References

- [Product requirements](../../product/tapoda-rebuild-prd.md#6-proposed-canonical-lifecycle--pending-g2)
- [Current flow ledger](../../rebuild/current-flow-ledger.md#4-application-and-confirmation-flows)
- [Module blueprint](../../rebuild/module-blueprint.md)
- [Form contract](form-contract.md)
