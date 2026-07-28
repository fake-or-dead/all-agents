# Tapoda Course Lifecycle

Tapoda manages the full lifecycle of Dhamma course applicants, participants, staff, and alumni. This glossary defines the language used by the rebuilt product and migration work.

## People and access

**Person**:
A human identity shared across accounts, applications, training history, and check-ins.
_Avoid_: User, member, applicant when referring to the human

**Account**:
The credentials and access state a person uses to sign in.
_Avoid_: Member profile, login user

**Applicant**:
A person who has started or submitted an application for one course session.
_Avoid_: Candidate, trainee before selection

**Participant**:
An applicant who has confirmed attendance for a course session.
_Avoid_: Attendee before confirmation

**Alumni**:
A person with an active alumni eligibility record from a verified completion or an approved legacy rule.
_Avoid_: Old applicant, old student, inferred approved person

**Alumni eligibility**:
An auditable reason a person receives the alumni application flow.
_Avoid_: Prior status check, old-member flag

**Staff applicant**:
An applicant requesting a course-staff role before assignment.
_Avoid_: Course staff, worker applicant

**Course staff**:
A person assigned to help operate a course session.
_Avoid_: Staff applicant, worker, employee

**Reviewer**:
An authorized person who assesses submitted applications and records a review.
_Avoid_: Approver, selector

**Check-in operator**:
An authorized person who verifies participant arrival.
_Avoid_: Receptionist, public check-in user

## Course structure

**Course**:
The reusable definition of a Dhamma program, including type, eligibility, and descriptive content.
_Avoid_: Class, event

**Course session**:
A scheduled run of a course at one center with dates, capacity, teachers, and operational settings.
_Avoid_: Course instance, batch

**Center**:
A physical Tapoda location that hosts course sessions.
_Avoid_: Branch, venue

**Registration policy**:
The eligibility, capacity, category, and opening-window rules for a course session.
_Avoid_: Gender switch, registration flag

## Application lifecycle

**Application**:
A person's course-session-specific lifecycle record. It owns drafts, immutable submissions, and status events; review rounds, invitations, and attendance reference it from their owning modules.
_Avoid_: Apply course, registration, submission

**Draft**:
An editable application that has not been submitted.
_Avoid_: Applying

**Application submission**:
An immutable revision frozen from an application draft and used as the evidence for review, receipt, and reporting.
_Avoid_: Latest answers, current application data

**Submitted**:
An application ready for administrative review.
_Avoid_: Applied, applicant pending

**Under review**:
A submitted application currently being assessed.
_Avoid_: Pending approval

**Invited**:
An application selected for the session and awaiting the person's confirmation.
_Avoid_: Approved, accepted

**Confirmed**:
An invited person who has accepted attendance and completed required confirmation information.
_Avoid_: Approved

**Checked in**:
A confirmed participant whose arrival was verified.
_Avoid_: Checkin, attended

**Completed**:
A checked-in participant whose course participation was finalized.
_Avoid_: Finalize

**Review round**:
One evaluation cycle for an exact application submission. It owns reviewer assignments, submitted reviews, and a resulting review decision.
_Avoid_: Review status, approval attempt

**Submitted review**:
A reviewer's immutable assessment of one application submission within one review round.
_Avoid_: Reviewer status, latest review

**Review decision**:
The immutable course-session selection outcome and reason for one review round, separate from the application's lifecycle state.
_Avoid_: Status update, approval status, submitted review

**Declined invitation**:
An invited applicant's decision not to attend, distinct from reviewer rejection.
_Avoid_: Rejected

**Status event**:
An immutable record of an application transition, including actor, time, source, and reason.
_Avoid_: Status log

**Action token**:
A hashed, expiring, one-use credential for an invitation, confirmation, cancellation, or recovery action.
_Avoid_: Apply token, encrypted ID

## Forms and records

**Form definition**:
The named purpose and ownership of a configurable form.
_Avoid_: Question group

**Form version**:
An immutable published arrangement of sections, questions, choices, rules, and consent references.
_Avoid_: Current questions

**Semantic key**:
A stable business identifier for a question or choice across database and form versions.
_Avoid_: Numeric question ID

**Answer snapshot**:
The stored value plus rendered question and choice labels captured at submission time.
_Avoid_: Latest answer

**Training experience**:
One verified or declared prior course experience.
_Avoid_: Training counter, training history info

**Application profile snapshot**:
The identity and profile values used for one submitted application.
_Avoid_: Current member profile

**Consent document version**:
The immutable legal text accepted by a person at a recorded time and context.
_Avoid_: Privacy checkbox, PDPA flag

## Operations

**Course workspace**:
The course-session-centered workspace for review, invitation, confirmation, check-in, participant operations, communication, and reporting.
_Avoid_: Backend page, admin dashboard

**Work queue**:
A cross-course list of tasks or exceptions needing an authorized person's action.
_Avoid_: Status tab, notification list

**Identity verification event**:
An audited result from card-assisted or manual identity verification.
_Avoid_: Card data dump

**Facility request**:
A participant's dinner, seating, laundry, or other course-operation need.
_Avoid_: Additional info

**Notification message**:
An immutable request to send one versioned communication to one recipient.
_Avoid_: Email call

**Export job**:
An audited asynchronous request to create a report artifact.
_Avoid_: Download action

**Legacy identifier**:
A source-system identifier retained only for migration traceability and compatibility.
_Avoid_: Primary business key
