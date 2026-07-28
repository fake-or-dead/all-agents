# Tapoda Next — Product Requirements Document

**Status:** Implementation-ready after mandatory discovery gates

**Source baseline:** `uat-20260526` at `3d2c3a4`

**Prepared:** 2026-07-28

**Product language:** Thai-first; English administration terms where established

**Target accessibility:** WCAG 2.2 AA

## 1. Executive decision

Rebuild Tapoda as a Laravel modular monolith with a course-centered Lifecycle Workspace, cross-course work queues, a versioned form engine, explicit application state machine, scoped RBAC, audited communications, and a secured optional Thai ID reader.

Preferred stack:

- Laravel 13 on PHP 8.5.
- React 19 and TypeScript through Inertia 3.
- Tailwind CSS 4 and shadcn/ui.
- PostgreSQL 18.
- Redis and Laravel Horizon.
- Postmark for transactional email.
- Signed email verification link through Postmark. If OTP is contractually required, store only a keyed HMAC with challenge ID, expiry, attempt counter, resend invalidation, and atomic one-use redemption; Twilio Verify remains a fallback adapter.
- S3 versioned object storage.
- PhpSpreadsheet queued exports.
- AWS Thailand Region `ap-southeast-7`, subject to the regional availability gate.

This is a behavior-preserving rebuild, not a visual reskin. All verified workflows remain covered. Unsafe legacy behavior is replaced, not preserved.

## 2. Evidence and confidence

Repository inspection found:

- 109 concrete source route declarations: 99 web and 10 API. Runtime availability is conditional: the preview route is local-only, the local mail preview returns 404 outside local, and `/accept/done` has no callable action.
- 110 endpoint-like declarations including one commented `/signin`, plus 10 group/prefix/middleware references: 120 total `Route::` references.
- 22 controllers.
- 21 models.
- 91 Blade views plus one README in the view directory.
- 21 SQL files: 19 change/runbook scripts plus `country.sql` and `thailand.sql` reference datasets.
- 12 test-support PHP files: 10 `*Test.php` cases plus two support files.
- 12 user-facing PDF assets.
- Laravel 8.83.23 and Laravel Mix 6. Public pages load Bootstrap 5.1.3 while `public/css/style.css` embeds Bootstrap 4.3.1; backend AdminLTE uses Bootstrap 4.6.1 JavaScript and jQuery.
- Synchronous queue configuration and disabled scheduled work.
- No confirmed CI pipeline.
- A Docker Compose definition without a repository Dockerfile.
- Manual SSH and Git-based deployment guidance.

High-change files:

- `app/Http/Controllers/ApplyController.php` — about 80 KB.
- `app/Http/Controllers/BackendReportController.php` — about 60 KB.
- `app/Http/Controllers/BackendApproveController.php` — about 52 KB.
- `app/Http/Controllers/BackendExportController.php` — about 44 KB.

Static inspection is complete enough for product scope and target design. It is not enough for production data migration. Repository migrations do not recreate the deployed schema. PHP, Composer, a live database, and Docker were unavailable during this assessment.

### 2.1 Source-of-truth order

1. Live production DDL and profiled data.
2. Approved product decisions and legal requirements.
3. Deployed route, email-link, document, report, and hardware behavior.
4. Repository implementation.
5. Manual SQL and task notes.

Conflicts must be recorded in the compatibility ledger. No silent inference.

### 2.2 BA/SA discovery package

The implementation baseline is the linked, uncommitted discovery package:

- [`../rebuild/current-page-inventory.md`](../rebuild/current-page-inventory.md) and [`../rebuild/current-page-inventory.csv`](../rebuild/current-page-inventory.csv): canonical page/endpoint register.
- [`../rebuild/current-flow-ledger.md`](../rebuild/current-flow-ledger.md): current actor journeys, branches, state effects, failures, and parity proof.
- [`../rebuild/coverage-matrix.md`](../rebuild/coverage-matrix.md): route/page/artifact/module reconciliation and unresolved production gates.
- [`../rebuild/module-blueprint.md`](../rebuild/module-blueprint.md): target module ownership, interfaces, implementations, seams, adapters, and migration order.
- [`../rebuild/ci-design-system.md`](../rebuild/ci-design-system.md): one Corporate Identity and CSS system for web, legacy compatibility, email, and print.
- [`../../CONTEXT.md`](../../CONTEXT.md): canonical domain language.

Every current endpoint and page artifact needs one target feature, compatibility adapter, archive decision, or approved retirement. Later UX/UI work must update the flow ledger instead of replacing undocumented behavior.

## 3. Goals

- Preserve every verified applicant, alumni, staff, reviewer, check-in, reporting, communication, and document capability.
- Replace ambiguous statuses with one explicit lifecycle.
- Make every transition authorized, validated, atomic, and audited.
- Let authorized staff understand a course from one workspace.
- Let applicants see progress, remaining tasks, deadlines, and outcomes.
- Replace numeric-ID-driven questions with immutable versioned forms.
- Protect personal, health, identity, and consent data by default.
- Make email, exports, scheduled work, and card reads observable and retryable.
- Support staged migration with reconciliation, rollback, and active-link compatibility.
- Provide a maintainable architecture with deep modules and narrow interfaces.
- Control all new UI through one versioned Design System: semantic tokens, primitives, workflow patterns, page templates, and generated web/email/print adapters.

## 4. Non-goals

- Microservices at launch.
- A separate public mobile application.
- Payment, LINE, social login, SMS OTP, analytics, or marketing automation without an approved requirement.
- A paid embedded map SDK.
- Mandatory smart-card hardware for check-in.
- Automatic retirement of legacy routes, PDFs, reports, emails, or placeholder workflows without owner and usage evidence.
- Bidirectional dual-write between legacy and new systems.

## 5. Actors and permissions

| Actor | Primary needs | Sensitive access |
|---|---|---|
| Visitor | Discover courses, eligibility, locations, documents | Public data only |
| Applicant | Manage account/profile, apply, confirm, cancel, view history | Own records only |
| Alumni | Reuse profile and training history, apply through alumni flow | Own records only |
| Course staff | Complete staff-specific application and operational details | Own records; assigned duties |
| System administrator | Manage identities, roles, configuration, integrations | Explicitly audited broad access |
| Course manager | Configure and operate assigned course sessions | Assigned center/session |
| Selection reviewer | Review and decide assigned applications | Assigned session; approved sensitive fields |
| Teacher reviewer | Inspect and print selected participant details | Assigned session; limited health/emergency need |
| Check-in operator | Verify confirmed participants and record arrival | Assigned session; minimum identity data |
| Operations staff | Manage attendance, facilities, laundry, and exceptions | Assigned session |
| Reporting/export user | Run approved reports and exports | Scoped fields; every export audited |
| Support user | Resolve account and link issues | Time-limited, reason-required access |
| System worker | Deliver notifications, generate exports, enforce schedules | Machine identity; least privilege |

### 5.1 RBAC rules

- Permissions combine role, resource action, center scope, and course-session scope.
- Health, mental-health, substance-use, medication, national ID, emergency contact, and exports have separate permissions.
- A person cannot approve their own privileged access.
- Support access requires a case reference and expires.
- Every sensitive record read, export, status transition, and role change creates an audit event.
- Applicant ownership checks apply to every route and mutation.

The complete role/action/resource matrix requires product-owner and privacy-owner approval before build Gate 2.

## 6. Proposed canonical lifecycle — pending G2

```text
draft
  → submitted
  → under_review
  → invited
  → confirmed
  → checked_in
  → completed
```

Terminal or side states:

```text
rejected
declined_invitation
cancelled_before_confirmation
withdrawn_after_confirmation
no_show
```

Rules:

- Review decision is separate from application state.
- Every transition records previous state, new state, actor, source, reason, and time.
- Only `ApplicationWorkflow` may transition an application.
- Notifications are requested through the same transaction using an outbox event.
- Required communication failure never disappears. Delivery has its own retryable state.
- Bulk transitions use an idempotency key and produce per-record results.
- Completion creates or verifies alumni eligibility.
- Historical labels remain snapshots. Display-language changes do not rewrite history.

### 6.1 Legacy state reconciliation

| Legacy value | Proposed state | Migration rule |
|---|---|---|
| `draft` | `draft` | Direct |
| `applying` | `draft` | Preserve last completed step |
| `applicant_pending` | `submitted` or quarantine | Require submission evidence |
| `applied` | `submitted` | Direct |
| `approved` | `invited` | Confirm with notification and confirmation evidence |
| `invited` | `invited` | Direct |
| `accepted` | Unresolved | Mandatory record-level rule |
| `confirmed` | `confirmed` | Direct |
| `checkin` | `checked_in` | Direct |
| `finalize` | `completed` | Direct |
| `rejected` | `rejected` or `declined_invitation` | Use actor, `invite_accept.accept_status`, and invitation timestamp |
| `canceled` | `cancelled_before_confirmation` or `withdrawn_after_confirmation` | Use confirmation time |
| `left` | `withdrawn_after_confirmation` | Confirm operational meaning |

Do not infer semantic equality from current Thai labels. `approved` and `confirmed` currently share a label despite different behavior.

### 6.2 Legacy alumni eligibility

Current guided flow treats a person as alumni after any prior `approved`, `confirmed`, `checkin`, or `finalize` application. The target definition uses an auditable alumni-eligibility event.

- Preserve existing flow assignment through a migration-generated legacy eligibility event until G2 approves a recalculation policy.
- Record source application, source state, rule version, and migration batch.
- New eligibility normally comes from verified completion.
- Never silently move a legacy alumni user into the new-person flow.

## 7. Complete current feature inventory

Every item below is required for parity, an explicit replacement, or a signed retirement decision.

### 7.1 Public discovery

- Home page.
- Course catalog and course detail.
- Year, month, and course-type filters.
- Center filter, corrected so its visible control changes results.
- Registration open/close window.
- Invite-only messaging.
- Eligibility by age, gender, applicant category, and course policy.
- Capacity or availability messaging by category.
- Course attachments.
- Course location and outbound map link.
- Suggestion, applicant-qualification, and about pages.
- Province, amphoe, tambon, country, language, education, prefix, tutor-type, and trainee-type reference data.
- Crawlable metadata, shareable GET filters, fast first render, and graceful JavaScript failure.

### 7.2 Account and identity

- Registration.
- Email verification by OTP or signed link.
- Personal-ID/password login.
- Defined passport/foreign-national behavior.
- Consent capture.
- Safe password recovery.
- Profile view and edit.
- Application list, resume, and history.
- Training history.
- Password change.
- Existing password-hash migration and rehash on login.
- Duplicate-person and account-merge policy.
- Minor/guardian consent policy when applicable.
- Administrator-account list, create, edit, deactivate, safe credential recovery/reset, role/scope assignment, self-disable prevention, last-administrator protection, and audit.

Forbidden replacement behavior:

- No plaintext password in email, browser storage, response payload, or logs.
- No personal-ID-only password reset.
- No account enumeration.
- No state-changing GET.

### 7.3 Guided application variants

Retain all four current flow families:

| Variant | Required stages |
|---|---|
| Initial application, new person (`pre-new`) | Profile → preferences → consent |
| Initial application, alumni (`pre-alumni`) | Profile → training history → preferences → consent |
| Post-invitation confirmation, new person (`post-new`) | Profile → preferences → teacher details → commitment → management |
| Post-invitation confirmation, alumni (`post-alumni`) | Profile → training history → preferences → teacher details → commitment → management |

Conditional behavior:

- Trainee or course staff.
- New person or alumni.
- Lay or monastic.
- Male, female, or explicitly approved category rule.
- Course type and tutor type.
- D10M manager details.
- Staff applicant before assignment, distinct from assigned course staff.
- Shared profile synchronization.
- Training counts and row-level history.
- Part-time dates, arrival/departure periods, and time ranges.
- Group 11/12 preferences.
- Group 14 teacher details.
- Group 9 commitment.
- Group 13 management.
- Four travel choices.
- Dinner and seating.
- Applicant companion or representative.
- Emergency contact.
- Risk and property commitments.
- Versioned PDPA consent.

Unknown gender must never silently select a male question group.

### 7.4 Legacy application and acceptance

- Inventory every legacy form, save route, acceptance route, token, and field.
- Preserve editing only until guided-flow parity is proven.
- Disable insecure direct-call endpoints when the replacement is enabled.
- Hiding a legacy view does not count as retirement.
- Historical applications stay readable.
- Route outcomes: `adapter`, `redirect`, `migrate`, or `remove`.

### 7.5 Applicant actions

- Save draft and autosave.
- Resume at the last valid step.
- Submit after server-side completeness validation.
- View timeline and next task.
- Accept invitation.
- Decline invitation with an applicant-authored reason; keep distinct from reviewer rejection.
- Complete post-invitation questions.
- Confirm attendance.
- Cancel before confirmation.
- Withdraw after confirmation.
- See immutable receipt and communication history.
- Receive accessible errors without losing answers.

Current cancellation and confirmation token behavior must be replaced by hashed, expiring, one-use action tokens. Active Laravel-encrypted links require a restricted compatibility exchange.

### 7.6 Course administration

- Create and edit course definitions and sessions.
- Configure dates, center, teachers, policy, documents, and registration window.
- Open/close registration by explicit category and capacity rule.
- Manage course-scoped check-in access.
- View counts by lifecycle and applicant classification.
- Search, filter, and inspect applicants.
- Bulk invite, request confirmation, cancel, mark no-show, check in, and complete.
- Convert an applicant to course staff with a recorded position and reason.
- Preserve related and prior application context.
- Finalization updates alumni eligibility.
- Every action has preview, authorization, idempotency, audit, and partial-failure reporting.

### 7.7 Review and selection

- Queues for new/alumni, male/female or approved category, monastic, and course staff groupings.
- Willingness-to-assist filter.
- Full application detail.
- Related current and prior applications.
- Versioned question-answer history.
- Training history.
- Part-time attendance.
- Confirmation date.
- Invitation older-than-15-days signal.
- Review notes, scores where approved, decision, reason, and conflict detection.
- Independent review decision and lifecycle transition.
- Optional trainee-to-staff conversion.

Verified current limitation:

- `BackendApproveController::store()` replaces `apply_course.status` and updates the single `invite_accept` row. It does not persist a first-class review, reviewed submission, reviewer note, score, decision revision, or complete transition event.
- Bulk course actions also replace `apply_course.status`.
- The current application rows provide course history, but only the latest mutable outcome is explainable. Staff cannot reliably answer who reviewed which submitted revision, what changed, or why the result differed between courses.

### 7.8 Invitation and confirmation

- Welcome, invitation, request-confirmation, confirmation, cancellation, recovery, and operational variants.
- Recipient, audience, course, template, attachment, and map rules.
- Versioned previews.
- Delivery queue, provider message ID, retry, bounce, and failure status.
- Active-link compatibility.
- Scheduled overdue confirmation requests.
- Idempotent resend with cooldown.
- No status is falsely marked “notified” after delivery-request failure.

### 7.9 Check-in

- Course-scoped operator authentication.
- Search confirmed participant by personal or passport ID.
- Manual identity verification.
- Optional Thai ID card-assisted verification.
- Thai/English name comparison.
- Explicit mismatch policy: warn or block by approved course policy.
- Duplicate-scan idempotency.
- Dinner, seating, facility, and attendance visibility.
- Arrival state and event history.
- Device readiness, timeout, retry, and supported-OS guidance.
- Minimum-data storage; no raw card response, address, or photo without legal approval.

### 7.10 Thai ID companion

Current behavior reads national ID, Thai/English names, date of birth, gender, address, and photo through `/status`, `/read-card`, and `/debug`.

Replacement requirements:

1. Bind only `127.0.0.1`.
2. Require a short-lived signed challenge.
3. Require an explicit operator action.
4. Validate origin, expiry, and one-use nonce.
5. Return only approved fields.
6. Sign the response for server verification.
7. Expose safe version and health details.
8. Provide a signed Windows installer.
9. Provide a signed and notarized macOS package.
10. Support controlled update and rollback.
11. Never make the device mandatory.
12. Preserve an audited manual path.

### 7.11 Applicant report

- Eight existing report groups.
- Seniority ordering.
- Sticky identity columns.
- Identity and classification.
- Application state and relevant dates.
- Health, mental-health, substance-use, medication, and history fields.
- Training history.
- Attendance and part-time periods.
- Confirmation/check-in/completion context.
- Eight-sheet Excel parity.
- Approved state-membership rule for each tab, counter, print view, worksheet, and export; screen/export differences are explicit.
- Field-level permission and export audit.

### 7.12 Teacher report and print

- Five existing participant groups.
- Group counters.
- Full detail modal.
- Maximum-ten selection rule unless owner retires it.
- Printable multi-page participant sheets.
- Emergency contact.
- Health and medication information approved for teacher use.
- Part-time attendance.
- Dinner, seating, and special requests.
- Stable ordering and type labels.
- Approved state-membership rule per new/alumni group, screen, counter, and print output.

### 7.13 Laundry and facilities

- Laundry operation list and filters.
- Male/female or approved category segmentation.
- Room.
- Day columns `01` through `08`.
- Laundry cost.
- Purchase cost.
- Total.
- Facility needs.
- Excel export.
- Course/session scope.
- Audited updates and downloads.

### 7.14 Documents

Inventory and preserve or explicitly retire:

- `apply-form.pdf`
- `applyform-for-board.pdf`
- `applyform-for-long.pdf`
- `guideline-registration-2025.pdf`
- Older registration guideline
- `manual.pdf`
- `new-privacy.pdf`
- `privacy.pdf`
- `practice-dhamma-worker.pdf`
- `practice.pdf`
- `training-intro.pdf`
- `public/uploads/course/course-attachment.pdf`

Observed active references:

- 2025 guideline during sign-up.
- New privacy document during application/consent.
- Old privacy document during registration agreement.
- Training introduction in invitation and request-confirmation messages.
- Practice documents in staff and trainee confirmation.

All documents gain checksum, version, visibility, purpose, locale, retention, and compatibility URL metadata.

### 7.15 Navigation and responsive behavior

- Public navigation.
- Applicant mobile navigation.
- Desktop course workspace navigation.
- Work queues.
- Breadcrumbs and course switcher.
- Equivalent mobile and desktop outcomes.
- No user-agent-specific template forks.

## 8. New workflow and UX

| Current workflow | Improved workflow |
|---|---|
| Status labels differ by controller and report | One proposed lifecycle after G2 approval; decision remains separate |
| Applicant searches pages and email for the next step | Timeline, one primary task, deadline, and last-saved state |
| Separate mobile/desktop templates drift | One responsive outcome with mobile cards and desktop grids |
| Admin work is split by backend route | Course Workspace concentrates course-session operations |
| Staff scan every course manually | Cross-course work queues surface aging and failures |
| Question visibility depends on IDs and duplicated JavaScript | `FormEngine` returns a versioned, persona-aware schema |
| A customer’s course row exposes only the latest review/status | Per-course review rounds retain reviewer, submitted revision, evidence, reason, and decision history |
| Bulk action can mix status and email failure | Preview, idempotency, outbox, retry, audit, per-record result |
| Check-in device is assumed and unauthenticated | Device readiness, signed challenge/assertion, manual fallback |
| Reports duplicate grouping and status logic | Approved status-membership matrix and versioned report specification |

### 8.1 Applicant experience

Home state is task-first:

1. Current application card.
2. Lifecycle timeline.
3. One primary next action.
4. Deadline or waiting state.
5. Last saved time.
6. Contact/support route.

Form behavior:

- Autosave after a valid field pause and on step navigation.
- Visible step name, progress, and incomplete-field summary.
- Conditional sections announce changes accessibly.
- Thai labels lead; English identity fields appear where legally needed.
- Server validation is authoritative.
- Return to the exact failed field.
- Mobile controls fit 320 px without horizontal scrolling.
- Submitted answers render as a receipt against the submitted form version.

### 8.2 Course Workspace

Each course session has:

- **Overview:** dates, registration policy, capacity, counts, risks, integration health.
- **Applications:** search, saved filters, applicant cards/grid, lifecycle actions.
- **Review:** assignments, conflicts, notes, decisions, aging.
- **Invitations:** audience preview, delivery state, overdue confirmation.
- **Participants:** confirmed, withdrawn, no-show, completed.
- **Check-in:** readiness, lookup, card/manual verification, arrival events.
- **Operations:** staff conversion, attendance, laundry, facility needs.
- **Communications:** templates, previews, queue, retries, bounces.
- **Reports:** applicant, teacher, laundry, print, queued exports.
- **Documents:** versions, public/private rules, legacy URLs.
- **Audit:** lifecycle, sensitive access, bulk actions, exports.
- **Settings:** policy, permissions, check-in access, integrations.

### 8.3 Cross-course work queues

- Submitted applications awaiting assignment.
- Reviews overdue.
- Invitations unsent or failed.
- Confirmation overdue.
- Card-reader or check-in exceptions.
- Failed exports.
- Data migration quarantine.
- Expiring documents or consent versions.
- Integration failures.

### 8.4 Data-heavy UI

- Desktop grid with pinned identity columns and column visibility.
- Mobile card view with equivalent fields and actions.
- Keyboard navigation, clear focus, no color-only status.
- Sensitive columns hidden unless permission is present.
- Export reflects authorized fields, not merely visible columns.

### 8.5 Design direction

- Calm, Thai-first editorial design.
- High information density for operations; focused single-task pages for applicants.
- Warm earth/stone identity; accessible deep brown for brand, green for primary action/completion, blue for links/information, amber for attention, and red for blocked or destructive action.
- Sarabun self-hosted for Thai body text.
- Minimum 44 px touch targets.
- WCAG 2.2 AA contrast, focus, labels, status announcements, and error summaries.

## 9. Architecture

### 9.1 Chosen shape

One Laravel deployable with deep domain modules. HTTP controllers, queue workers, scheduled commands, CLI migration tools, and compatibility routes are adapters.

Deep business modules:

1. Identity & Access.
2. People & Profiles.
3. Reference Data.
4. Course Catalog & Sessions.
5. Form Engine.
6. Application Workflow.
7. Review & Selection.
8. Invitations & Confirmations.
9. Check-in & Attendance.
10. Operations & Facilities.
11. Notifications.
12. Reports & Exports.
13. Documents & Consent.
14. Audit & Compliance.

Transitional and technical modules:

15. Legacy Compatibility.
16. Design System.
17. Platform Operations.

`Course Workspace` is a composed experience over module projections. It owns no lifecycle state.

Core interfaces:

```php
interface ApplicationWorkflow
{
    public function start(StartApplication $command): ApplicationResult;
    public function saveDraft(SaveApplicationDraft $command): DraftResult;
    public function submit(SubmitApplication $command): ApplicationResult;
    public function transition(TransitionApplication $command): TransitionResult;
}

interface FormEngine
{
    public function schemaFor(FormContext $context): FormSchema;
    public function validate(FormContext $context, AnswerSet $answers): ValidationResult;
    public function freeze(PublishedFormVersion $version, AnswerSet $answers): FrozenFormAnswers;
    public function handle(FormDefinitionCommand $command): FormDefinitionResult;
}

interface Notifications
{
    public function request(NotificationRequest $request): NotificationReceipt;
    public function status(NotificationId $id): NotificationStatus;
}

interface IdentityVerificationWorkflow
{
    public function issueChallenge(IssueIdentityChallenge $command): IdentityChallenge;
    public function verifyAssertion(VerifyIdentityAssertion $command): IdentityVerificationResult;
}

interface ReportExporter
{
    public function request(ExportRequest $request): ExportJob;
}
```

The workstation browser owns the local companion adapter:

```ts
interface IdentityReader {
  read(challenge: IdentityChallenge): Promise<SignedIdentityAssertion>;
}
```

Flow: Laravel issues a challenge → browser TypeScript adapter calls the paired `127.0.0.1` companion → companion returns a signed minimum-data assertion → browser submits it to `IdentityVerificationWorkflow`. Device keys support pairing, rotation, and revocation. Unsigned remote CORS configuration is removed.

Architecture vocabulary:

- `ApplicationWorkflow` is deep: its interface is narrow while state rules, authorization, auditing, and outbox implementation remain internal.
- `FormEngine` creates one seam for form definition/version publication, persona applicability, visibility, validation, and deterministic answer freezing. Application Workflow owns draft answers, submissions, and persisted snapshots.
- Form Studio is an authorized administration adapter to `FormEngine::handle`; publication remains internal implementation, never another state owner.
- Business modules call `Notifications::request`. An outbox worker alone invokes internal `MailGateway::send` through Postmark or deterministic-fake adapters.
- `IdentityVerificationWorkflow` creates the server seam; browser and companion implementations never make Laravel call an operator's loopback address.
- Provider integrations use production and fake adapters. Two adapters justify each seam.
- Reports gain leverage from one versioned specification per output rather than duplicated query logic.
- Course Workspace improves locality by concentrating course operations around the course-session module.
- Current controllers are shallow; their interface nearly matches scattered implementation details.

### 9.2 FormEngine detailed design

#### Current maintenance defects

Repository evidence:

- `ApplyQuestionServices.php` loads and normalizes the same concepts through `basicQuestion`, `userDetail`, `trainingDetail`, `customQuestion`, `questionsByIds`, `question`, and `loadDetailQuestionsForIds`.
- `ApplyController.php` reconstructs required/conditional validation separately for preferences, teacher details, commitment, and management details.
- `preferences.blade.php`, `teacher-details.blade.php`, `commitment.blade.php`, and `management-details.blade.php` each implement question rendering, dependency evaluation, error focus, CSS, and JavaScript.
- View logic guesses presentation from Thai label length and numeric IDs, including question `55` as textarea and question `43` as a phone.
- Conditional rules depend on numeric relations such as `39 → 40–43`, `46 → 47`, and `48 → 49`.
- `deriveTriggerValues()` infers behavior from legacy choice values, including misspellings such as `nerver` and `alway`.
- Group/persona behavior is encoded in arrays such as `[7, 8, 9]`, `[3, 4, 5, 58, 59]`, and fixed groups `11–14`.
- Hidden-answer clearing is not one policy. Some steps clear dependent values; other steps can retain stale answers.
- Management form persistence also confirms the application and requests email delivery. Form and lifecycle behavior are coupled.
- Manual SQL assumes exact numeric question and choice sequences.

Refactor goal: callers receive one complete form schema and submit semantic-keyed values. They do not know legacy IDs, persona arrays, visibility mechanics, persistence tables, or field-rendering heuristics.

#### Deep interfaces

```php
interface FormEngine
{
    public function schemaFor(FormContext $context): FormSchema;
    public function validate(FormContext $context, AnswerSet $answers): ValidationResult;
    public function freeze(PublishedFormVersion $version, AnswerSet $answers): FrozenFormAnswers;
    public function handle(FormDefinitionCommand $command): FormDefinitionResult;
}
```

Form Engine owns definitions, versions, rule validation, and publication. Application Workflow owns application drafts, draft answers, submissions, and persisted snapshots. Form Studio is an authorized administration adapter. No interface exposes storage tables or rule-evaluator internals.

#### Form context

`FormContext` contains only facts required to select a published version and evaluate applicability:

- Form key: `initial_application`, `post_invitation_confirmation`, or another approved purpose.
- Course-session ID and course-type key.
- Phase.
- Applicant intent: trainee or staff applicant.
- Alumni-eligibility key and provenance.
- Lay/monastic category.
- Approved gender/category value; never a silent male fallback.
- Locale.
- Draft ID and optimistic-lock revision.

The server derives context from authorized records. The browser cannot select a privileged persona by posting context fields.

#### Canonical schema

```ts
type FormSchema = {
  formKey: string;
  versionId: string;
  versionNumber: number;
  draftId: string;
  draftRevision: number;
  sections: FormSection[];
};

type FormSection = {
  key: string;
  title: string;
  description?: string;
  questions: QuestionDefinition[];
};

type QuestionDefinition = {
  key: string;
  type:
    | "short_text"
    | "long_text"
    | "phone"
    | "single_choice"
    | "multi_choice"
    | "select"
    | "date"
    | "repeatable_group"
    | "acknowledgement";
  label: string;
  helpText?: string;
  placeholder?: string;
  required: boolean | RuleExpression;
  choices?: ChoiceDefinition[];
  validation: ValidationRule[];
  visibility?: RuleExpression;
  hiddenAnswerPolicy: "clear" | "retain";
  rendererHint?: "cards" | "inline" | "textarea" | "table";
  initialValue: unknown;
};

type ChoiceDefinition = {
  key: string;
  value: string;
  label: string;
};

type ValidationRule = {
  rule: "required" | "min_length" | "max_length" | "pattern" | "phone" | "row_complete";
  parameters?: Record<string, string | number | boolean>;
  messageKey: string;
};

type RuleExpression =
  | { question: string; operator: "equals" | "not_equals"; value: unknown }
  | { question: string; operator: "in" | "not_in"; values: unknown[] }
  | { question: string; operator: "exists" }
  | { all: RuleExpression[] }
  | { any: RuleExpression[] }
  | { not: RuleExpression };
```

Allowed rule expression:

```json
{
  "all": [
    {
      "question": "operations.needs_dinner",
      "operator": "equals",
      "value": "yes"
    }
  ]
}
```

Supported operators are deliberately small: `equals`, `not_equals`, `in`, `not_in`, `exists`, `all`, `any`, and `not`. No executable PHP, JavaScript, SQL, or general expression language is stored.

#### Semantic key examples

| Legacy | Semantic key | Type |
|---|---|---|
| Q1 | `health.has_physical_condition` | `single_choice` |
| Q2 | `health.physical_condition_details` | `long_text` |
| Q15 | `health.has_recent_substance_use` | `single_choice` |
| Q16 | `health.substance_use_history` | `repeatable_group` |
| Q19 | `health.uses_prescribed_medication` | `single_choice` |
| Q20 | `health.prescribed_medication_details` | `long_text` |
| Q39 | `declaration.completed_by` | `single_choice` |
| Q40–43 | `declaration.representative.*` | typed dependent fields |
| Q46 | `operations.needs_dinner` | `single_choice` |
| Q47 | `operations.dinner_reason` | `long_text` |
| Q48 | `operations.can_sit_on_floor` | `single_choice` |
| Q49 | `operations.seating_reason` | `long_text` |
| Q57 | `operations.travel_method` | `select` |

Legacy values map explicitly:

- `nerver` → `never`.
- `alway` → `always`.
- Q57 choice IDs/values map to `self`, `center_outbound`, `center_return`, and `center_round_trip`.
- Raw legacy value and semantic canonical value remain available to migration reconciliation.

#### Rendering module

React uses one `QuestionSection` implementation and one field registry:

```ts
const questionRenderers = {
  short_text: ShortTextField,
  long_text: LongTextField,
  phone: PhoneField,
  single_choice: SingleChoiceField,
  multi_choice: MultiChoiceField,
  select: SelectField,
  date: DateField,
  repeatable_group: RepeatableGroupField,
  acknowledgement: AcknowledgementField,
} satisfies QuestionRendererRegistry;
```

Rules:

- Renderer receives a `QuestionDefinition`; it never checks legacy question ID.
- `QuestionSection` owns heading, progress, error summary, question numbering, and section description.
- Field renderer owns label, help, control, inline error, and accessibility association.
- A pure client rule evaluator updates visibility for immediate feedback.
- Server runs the authoritative evaluator before every draft save and submission.
- Server/client contract fixtures execute the same published rule examples to prevent drift.
- Hidden values follow the published `hiddenAnswerPolicy`; sensitive dependent answers default to `clear`.
- Repeatable groups use structured rows, stable row IDs, add/remove actions, minimum/maximum rows, and row-level errors. No fixed five-row UI.
- Requiredness and validation come only from published rules. Thai label length never changes control type.

#### Draft and submission behavior

1. Browser loads `schemaFor(context)`.
2. Browser posts semantic-keyed changes with `draftId`, `draftRevision`, and idempotency key.
3. `ApplicationWorkflow::saveDraft()` derives context again, asks Form Engine to evaluate/validate, clears hidden answers by published policy, and updates Workflow-owned draft answers through optimistic locking.
4. Revision conflict returns current revision plus field-level merge information; it never silently overwrites another session.
5. `ApplicationWorkflow::submit()` asks Form Engine to validate and freeze the whole applicable schema, writes the Workflow-owned immutable application submission, and emits an outbox event in one transaction.
6. Lifecycle transition remains in `ApplicationWorkflow`. Form submission never directly confirms, invites, emails, or changes application state.

#### Form Studio

Authorized administrators can:

- Clone a published version into a draft.
- Add/reorder sections and questions.
- Select semantic keys from a controlled registry.
- Configure label, help, type, choices, validation, applicability, visibility, and hidden-answer policy.
- Preview all approved personas and responsive sizes.
- Run publish checks: duplicate key, missing translation, invalid choice, broken reference, dependency cycle, unreachable question, required-hidden conflict, destructive value change, and report mapping.
- Compare draft versus published version.
- Publish an immutable version with author, approver, reason, and effective assignment.
- Roll back by assigning an older published version to future applications. Historical submissions never change.

Production publishing requires separate author and approver permissions.

#### Incremental refactor path

1. Capture characterization fixtures for every current group, persona, question, choice, visibility outcome, validation result, and stored answer.
2. Add `LegacyFormDefinitionAdapter` and `LegacyAnswerAdapter` that translate current tables to the canonical schema without changing storage.
3. Replace four duplicated Blade renderers with one shared question-section partial and one JavaScript/CSS asset as an interim step.
4. Move requiredness, trigger evaluation, collection parsing, and hidden-answer clearing from controllers/views into `FormEngine`.
5. Add semantic keys and explicit legacy mapping. Keep numeric IDs only inside legacy adapters.
6. Add versioned form tables and dual-read comparison. Do not dual-write from controllers.
7. Move authenticated flows to the React `QuestionSection` renderer.
8. Enable Form Studio only after report/export mappings and publish approvals are enforced.
9. Remove legacy loaders, `quest{id}` field names, numeric-ID filters, and duplicated Blade JavaScript after parity telemetry and G5/G6 acceptance.

#### Form-specific acceptance

- One renderer supports every approved question type.
- Adding a question to a draft form requires no controller/view code.
- A published form cannot change.
- Every submission records form version, semantic key, raw value, canonical value, and displayed label snapshot.
- Every persona matrix resolves without missing, duplicate, or cyclic questions.
- Hidden sensitive values clear according to policy.
- Draft autosave is idempotent and conflict-safe.
- Form submission cannot change lifecycle state directly.
- Golden fixtures cover all current groups, including `9`, `11`, `12`, `13`, and `14`.
- Reports resolve answers through semantic keys and submission snapshots.

### 9.3 Behavior-preserving weakness remediation

The rebuild preserves observable outcomes while replacing overwrite-only storage. The mature pattern is:

```text
immutable fact, snapshot, or event
            ↓
authorized current-state projection
            ↓
existing screen behavior during compatibility
```

The projection keeps the latest value easy to query. It is never the only record of what happened. Compatibility code may continue reading legacy-shaped fields during migration, but only the owning module writes the new source records and derives the projection.

#### Repository weakness ledger

| Current pattern | Failure mode | Better system with the same visible behavior | Migration and parity proof |
|---|---|---|---|
| `apply_course.status` is replaced by applicant, reviewer, bulk-action, check-in, and completion paths | Only the latest state survives; actor, reason, previous state, and failed side effects are incomplete | `ApplicationWorkflow` appends `application_status_events` and updates a current-state projection transactionally | Replay events to the same current status; compare every migrated application with the legacy row |
| Approval/rejection updates one application row and one `invite_accept` row | A customer’s review cannot be tracked independently per course, review round, or submitted revision | Review & Selection module records course-session review rounds, assignments, immutable submitted reviews, scores, notes, conflicts, and decisions referencing an exact submission | Import only provable actor/time/outcome; mark missing provenance `legacy_unknown`; course-by-course outcome parity |
| `users`, `contact`, and manager data are updated in place | Editing today’s profile can change how an old application appears in review and reports | Keep one current person profile for reuse; freeze `application_profile_snapshots` and manager details on every submission | Compare the snapshot with the legacy report at migration time; later profile edits must not alter it |
| `training_history_info` is one aggregate row per `uid` and reports load it by person | Later training edits can rewrite the evidence seen for an earlier course | Normalize verifiable `training_experiences`; preserve unexpandable legacy totals in `training_summaries`; freeze the reviewed training snapshot per submission | Never invent individual events from aggregate counts; reconcile the displayed legacy summary |
| `question_apply_course` answers can be updated while reports join current `question` and `question_choices` labels | Answer history, question wording, choice wording, and reviewed revision can drift | Mutable draft answers freeze into immutable submission answers with form version, semantic key, raw value, canonical value, and label snapshots | Golden fixtures compare every legacy answer and displayed label; corrections create superseding submissions |
| `invite_accept.accept_status` is updated and delivery is coupled to request paths | Resend, failure, bounce, actor, and invitation-versus-rejection history are difficult to explain | Invitation state, action-token redemption, message, delivery attempt, and lifecycle transition remain separate records connected by outbox events | Latest invitation projection matches legacy; delivery failure cannot falsely advance notification state |
| Check-in changes the application’s latest status | Arrival, manual fallback, identity check, correction, and operator evidence can collapse into one result | Check-in & Attendance module appends check-in and identity-verification events while exposing one current attendance projection | Current checked-in list stays identical; event replay proves actor, method, time, and correction |
| Course access and category behavior use flags, numeric arrays, and delimited values | Invalid combinations and hidden rules spread through controllers, views, and exports | Course-session policy rows, typed category keys, relations, and `FormContext` concentrate rules behind deep module interfaces | Characterization matrix proves the same eligible personas, teachers, capacities, and question groups |
| Binary administrator assumptions and route-level checks | Access is broader than the assigned center, course, action, or sensitive field requires | Scoped role assignments and separate sensitive-data permissions with access events | Deny-by-default RBAC matrix; legacy administrators receive explicit, reviewable transitional grants |
| Reports and exports join current tables and duplicate status/group logic | Historical output changes after profile, form, or status edits; screen/print/XLSX diverge | Versioned report specifications read immutable submissions and approved current projections; exports become audited artifacts | Golden screen, print, and eight-sheet XLSX comparisons before cohort cutover |
| Consent is collected as current form state | The accepted text/version and application context may not be reproducible | Immutable consent document versions and consent acceptances tied to person and optional submission | Preserve available timestamps and context; unresolved legacy consent enters quarantine |
| Public/local document files are treated as replaceable paths | Prior attachments, hashes, access class, and report references can disappear | Versioned document metadata, immutable object versions, checksums, classification, and signed access | Crawl legacy URLs, checksum files, map redirects, and retain historical references |

#### Per-course customer review model

One customer can have many applications. Each application belongs to exactly one course session. Every submitted review is therefore scoped to:

```text
person
  └─ application for course session
       ├─ submission revision 1
       │    └─ review round 1
       │         ├─ reviewer A → submitted review
       │         ├─ reviewer B → submitted review
       │         └─ decision → invited or rejected
       └─ superseding submission revision 2
            └─ review round 2 → correction or reconsideration
```

Required behavior:

- Course A and Course B reviews never overwrite one another.
- A review references the exact immutable submission it evaluated.
- Review assignment, review result, decision, invitation, and application lifecycle are separate concepts.
- A reviewer edits a private draft. Submitting freezes it. A correction creates a superseding review; it never mutates the prior review.
- A decision records outcome, reason code, optional approved note, actor, time, rule/version provenance, and the contributing reviews.
- `latest_review` and `current_decision` are projections for list screens, not source tables.
- Profile, training, form, and answer changes after submission do not change a historical review.
- Reconsideration creates a new review round and a new decision event.
- Applicant-visible reasons are explicitly classified and separated from private reviewer notes.
- Cross-course history shows one course card per application with submitted date, review round, outcome, reviewer provenance allowed by permission, and a link to the reviewed snapshot.

Legacy backfill cannot manufacture history. For each current row:

1. Create the application and best-supported submission snapshot.
2. Import provable status timestamps and `invite_accept.created_by` when present.
3. Create a `legacy_imported` status/decision record for the current outcome.
4. Set unknown reviewer, note, score, and reason to `NULL` with provenance `legacy_unknown`.
5. Retain source table, source key, raw status, migration batch, and confidence.
6. Quarantine contradictions instead of choosing a convenient result.

#### Compatibility rules

- Existing screens initially read projections shaped like `apply_course`, `contact`, `training_history_info`, and `invite_accept`.
- New writes go through one owning module interface. Controllers never update the projection directly.
- Side-by-side telemetry compares legacy value, derived projection, and immutable source record.
- Cutover requires zero unexplained differences for status, review outcome, profile snapshot, answer receipt, training summary, and report output.
- No behavior-preserving rule requires retaining unsafe authorization, missing audit, mutable history, or false notification success.

### 9.4 Rejected launch option

A Next.js frontend plus NestJS backend is valid only when committed mobile, partner, or multi-client interfaces require independent deployment. It adds two runtimes, harder Laravel-token and password compatibility, distributed transactions, and higher operational cost. Do not select it for UI novelty.

### 9.5 Runtime topology

- Web task: Blade-rendered public catalog/content and authenticated Inertia React workflows. No production Node SSR process.
- Worker task: Horizon consumers.
- Scheduler task: Laravel `schedule:work`.
- PostgreSQL primary with Multi-AZ resilience.
- Dedicated TLS-enabled Redis OSS/Valkey replication group for Horizon, cache, rate limiting, and coordination. Cluster mode is disabled because Horizon does not support Redis Cluster.
- S3 for public and private document/export artifacts.
- WAF and CDN at the edge.
- KMS and managed secrets.
- Central logs and metrics.
- Sentry or approved OpenTelemetry-backed monitoring.
- Health, readiness, queue lag, delivery failure, export failure, and migration reconciliation signals.

Preferred AWS Thailand deployment:

- ECS/Fargate where confirmed available.
- ECS on EC2 in the same region as fallback.
- RDS PostgreSQL 18 Multi-AZ where confirmed available.
- ElastiCache-compatible Redis OSS/Valkey replication group, TLS enabled and cluster mode disabled, where confirmed available.
- S3 with versioning.
- CloudFront and WAF.
- KMS, Secrets Manager, CloudWatch.

No service is approved until availability, data residency, backup region, RPO/RTO, and cost are signed off.

## 10. Target schema

Use ULID or UUID identifiers. Keep `legacy_id`, source table, and migration batch during the migration window.

### 10.1 Identity and authorization

- `accounts`: credentials, email, state, verification timestamps.
- `people`: legal identity and profile.
- `person_identifiers`: encrypted identifier, keyed HMAC lookup, country/type.
- `addresses`.
- `emergency_contacts`.
- `roles`.
- `permissions`.
- `role_assignments`: account, role, scope type/ID, validity window, grantor, reason, and support case reference.
- `role_permissions`.
- `authentication_events`.
- `account_recovery_tokens`.

National ID is encrypted at rest. Exact lookup uses a separately keyed HMAC. Raw national ID never appears in logs, URLs, analytics, or cache keys.

A person may have zero or more accounts. Account merge and recovery never duplicate the person record.

### 10.2 Reference and course data

- `countries`, `provinces`, `amphoes`, `tambons`.
- `languages`, `education_levels`, `prefixes`, `trainee_types`, `tutor_types`.
- `centers`.
- `course_types`.
- `courses`.
- `course_sessions`.
- `teachers`.
- `course_teachers`.
- `registration_policies`.
- `course_capacity_rules`.
- `course_documents`.

Delimited teacher codes and gender-specific open/closed columns are replaced by relations and explicit policy rows.

### 10.3 Application and review

- `applications`: mutable lifecycle envelope with `current_draft_id` and `current_submission_id`.
- `application_status_events`.
- `application_drafts`: mutable response set for initial, post-invitation, or correction work; includes current step, optimistic-lock version, and last-saved time.
- `application_draft_answers`: mutable autosaved answers owned by one draft.
- `application_draft_profiles`, `application_draft_manager_details`, and `application_draft_attendance`: mutable typed staging records.
- `application_steps`: progress owned by a draft.
- `application_submissions`: immutable submitted revision, form version, submitted time, and optional superseded submission.
- `application_profile_snapshots`: immutable and owned by one submission.
- `application_training_snapshots`: immutable reviewed training evidence owned by one submission; may reference normalized experiences and legacy summary provenance.
- `application_manager_details`: typed D10M manager data owned by one submission.
- `application_role_intents`: trainee or staff-applicant intent, distinct from an assignment.
- `alumni_eligibility_events`: person, source application/state, rule version, reason, and provenance.
- `review_rounds`: application, exact submission, sequence, reason, state, and opened/closed times.
- `review_assignments`: review round, reviewer, scope, assigner, conflict state, due time, and completion state.
- `review_drafts`: private mutable reviewer work with optimistic-lock version.
- `reviews`: immutable submitted review referencing the exact review round, submission, assignment, reviewer, reason codes, approved note, private note classification, provenance, and optional superseded review.
- `review_score_dimensions`: versioned approved scoring definition where scoring is required.
- `review_scores`: immutable dimension/value rows owned by one submitted review.
- `decisions`: immutable course-session decision referencing the exact review round and contributing reviews.
- `decision_events`: append-only reconsideration, supersession, and publication history.
- `invitations`.
- `action_tokens`.
- `application_cancellations`.
- `staff_assignments`.
- `legacy_state_imports`: source record, raw value, mapped fact, migration batch, confidence, and contradiction state.

Unique constraints prevent more than one active application per person and course session unless an approved exception exists.

### 10.4 Versioned forms

- `form_definitions`.
- `form_versions`.
- `form_version_drafts`.
- `form_sections`.
- `questions`.
- `question_semantic_keys`.
- `question_choices`.
- `visibility_rules`.
- `validation_rules`.
- `form_assignments`.
- `application_answers`: belongs to an immutable application submission.
- `legacy_question_mappings`: legacy group/question/choice identifiers and raw values mapped to semantic keys and canonical values.
- `form_publication_events`: author, approver, checks, reason, and published version.

Rules:

- Stable semantic keys plus legacy numeric identifiers.
- Published versions are immutable.
- Draft versions use optimistic locking and cannot receive applicant answers.
- Form version includes sections, choices, applicability, visibility, validation, and consent references.
- Visibility rules use the restricted declarative operators defined by `FormEngine`; publication rejects dependency cycles and unreachable questions.
- Answers store typed values and question/choice label snapshots.
- Answers store semantic key, raw migrated value when applicable, canonical value, and label snapshot.
- Repeatable collections are supported.
- Existing misspelled stored values remain mappable migration inputs, never new canonical values.
- Profile, training, manager, and attendance records remain typed entities, not generic answers.

### 10.5 Training, attendance, and operations

- `training_experiences`: one row per experience, duration, center, date, teacher, verification.
- `training_summaries`: provenance-preserving legacy aggregate counts when row-level experience does not exist.
- `attendance_plans`: full/part-time, dates, arrival/departure periods.
- `checkins`.
- `checkin_events`.
- `identity_verification_events`.
- `room_assignments`: course session, application, room, validity, assigner, reason, and audit fields.
- `facility_requests`: course session, application, typed need, details, state, resolution, and audit fields.
- `participant_service_entries`: course session, application, service date/day, service type including laundry, quantity, unit cost, state, and recorder.
- `participant_purchases`: course session, application, item/description, quantity, cost, purchaser, and recorded time.
- `operation_cost_adjustments`: target service/purchase, signed amount, reason, actor, and recorded time; original rows remain unchanged.

Operations & Facilities owns these records. Reports & Exports consumes its projection; screen and export cannot reimplement membership or totals.

### 10.6 Consent, audit, communications, and artifacts

- `consent_documents`.
- `consent_document_versions`.
- `consent_acceptances`: person, document version, context, time, evidence, and optional application/submission. Supports registration consent before an application.
- `audit_events`.
- `data_access_events`.
- `retention_policies`.
- `deletion_requests`.
- `notification_templates`.
- `notification_messages`.
- `notification_deliveries`.
- `outbox_events`.
- `export_jobs`.
- `export_artifacts`.
- `documents`.

### 10.7 Schema constraints

- Foreign keys on all owned relations.
- Unique semantic keys within a form definition.
- One current check-in per application.
- One-use action-token redemption enforced transactionally.
- Consent references immutable document versions.
- Audit events append-only.
- Published form versions cannot be updated.
- Submitted application revisions, profile snapshots, answers, and consents cannot be updated; a correction creates a superseding submission.
- Submitted reviews and decisions cannot be updated; correction or reconsideration creates a superseding review or decision event.
- Reviews, decisions, receipts, and applicant-visible reasons reference the exact reviewed/submitted revision and course session.
- Current application status, latest review, current decision, invitation state, and attendance state are projections derived from immutable records.
- Autosave updates only the current draft under optimistic locking.
- Submission transactionally freezes the draft into `application_submissions`, profile/manager/attendance snapshots, immutable answers, and consent evidence; then closes that draft.
- Post-invitation questions and corrections create a new draft and, when submitted, a superseding immutable submission.
- Export artifacts expire according to data classification.
- Time stored in UTC; Thai display uses approved timezone and Buddhist-year formatting.
- Thai search, sorting, collation, empty-string/`NULL`, and MySQL date-coercion behavior require migration fixtures.

## 11. External capability comparison

| Capability | Current | Options | Recommendation | Fallback | Data/privacy | Failure/operations | Cost shape |
|---|---|---|---|---|---|---|---|
| Email verification | Twilio Verify email OTP | Signed link, application OTP, Twilio Verify | Signed link delivered by Postmark | Keyed-HMAC OTP or Twilio adapter if OTP is required | Challenge ID; peppered HMAC; rate-limit email/IP/account; redact logs | Expiry, attempt counter, resend invalidation, atomic redemption, deterministic fake | Postmark message cost; no extra Verify charge |
| Transactional email | Synchronous SMTP | Postmark, SES, Resend | Postmark | SES at high volume | Minimize template payload; classify attachments | Queue, delivery ID, webhook, retry, bounce, dedupe | Per-message; SES cheaper but more operations |
| Queue | `sync` | Redis/Horizon, SQS | Redis and Horizon | SQS when scale/durability requires | Avoid PII in job names/logs | Queue lag, failed-job alert, retry policy | Redis runtime versus SQS usage |
| Database | MariaDB 10.5 | PostgreSQL 18, MySQL 8.4 LTS | PostgreSQL 18 | MySQL 8.4 if semantic migration is rejected | Encryption, HMAC lookup, scoped replicas | PITR, Multi-AZ, restore drills | Managed instance/storage/backup |
| Spreadsheet export | PHPExcel 1.8.2 | PhpSpreadsheet, CSV | Queued PhpSpreadsheet | CSV for large flat outputs | Artifact expiry; sensitive field permissions | Formula neutralization, audit, retry | Worker and object-storage usage |
| File storage | Public local disk | S3, compatible storage, MinIO | S3 with versioning | Compatible storage behind adapter | Public/private classes, signed URLs, retention | Checksum, lifecycle, restore, redirect monitoring | Storage, requests, CDN transfer |
| Thai ID reader | Custom unauthenticated PC/SC HTTP agent | Hardened companion, native messaging, WebUSB/HID | Hardened loopback companion | Audited manual verification | Return minimum fields; no raw payload/photo by default | Pairing, signed challenge, health, signed installers, rollback | Packaging/support cost |
| Maps | Outbound Google Maps link | Link, embedded Google Maps, OpenStreetMap | Keep outbound link | OpenStreetMap link | No applicant data in URL | Static fallback link | No paid SDK |
| Fonts | Google Fonts | Self-host | Self-host Sarabun/Kanit | System font stack | No third-party page request | Cache/version with app assets | Negligible storage |
| Monitoring | None confirmed | Sentry, OpenTelemetry provider | Sentry or approved OTel platform | CloudWatch-only minimum | PII scrubbing and sampling | Alert ownership, traces, release correlation | Event/retention based |
| Runtime hosting | Manual server deployment | ECS/Fargate, ECS/EC2, App Runner | ECS/Fargate in Thailand if available | ECS/EC2 same region | Residency and secrets policy | Immutable artifact, rolling deploy, rollback | Task/instance/load-balancer |
| Payments/LINE/SMS/social/analytics | No active evidence | Multiple | Do not add | None | New consent and vendor review required | New ownership required | Avoided |

Provider interfaces must have production and deterministic fake adapters. Provider selection is configuration, not business logic.

## 12. Security and privacy release gate

The rebuild cannot ship with any of these current patterns:

- Global CSRF exemption.
- Wildcard PII CORS.
- Public check-in PII lookup.
- State-changing GET for cancellation, delete, or logout.
- Base64 JSON treated as encrypted message data.
- Raw HTML title/message and arbitrary callback rendering.
- Plaintext passwords in storage, email, responses, or browser state.
- Personal-ID-only account takeover path.
- Public debug, test, mail, or `phpinfo()` routes.
- Backend login without rate limiting.
- Binary admin-only authorization.
- Raw SQL interpolation.
- Card companion bound to all interfaces without authentication.
- Spreadsheet formula injection.
- Status committed while required outbox persistence fails.
- Sensitive access without audit and retention.

Required security acceptance:

- CSRF, CORS, IDOR, rate-limit, XSS, open-redirect, replay, formula-injection, and privilege-escalation tests pass.
- Secrets come from the deployment platform and are rotated.
- Cookies are secure, HTTP-only, and use an approved SameSite policy.
- CSP, HSTS, and security headers are verified.
- Logs and monitoring redact PII and tokens.
- Threat model covers account recovery, public action links, bulk actions, exports, card companion, and migration tools.
- PDPA legal basis, retention, access, correction, deletion, and breach procedures are approved.

## 13. Migration and compatibility

### Phase 0 — establish production truth

- Obtain the external proposed schema named by the product owner.
- Capture live `SHOW CREATE TABLE`, indexes, constraints, collations, row counts, and data sizes.
- Profile statuses, rejection source/actor, invitation responses, alumni evidence, staff applications/assignments, aggregate training data, registration consent, question/choice IDs, course codes, teacher codes, personal-ID duplicates, nulls, orphans, invalid dates, and active links.
- Inventory routes, bookmarks, PDFs, attachments, email variants, and report fields.
- Freeze ad hoc schema edits.
- Approve retention, RPO, RTO, and backup region.

### Phase 1 — compatibility shell

- Preserve safe public URLs through explicit redirects or adapters.
- Preserve Thai labels, Buddhist-year formatting, attachments, report ordering, and active application visibility.
- Keep the legacy `APP_KEY` only in a restricted token-exchange implementation:
  1. Decrypt legacy token.
  2. Validate ownership, action, and current eligibility.
  3. Issue a new hashed, expiring, one-use token.
  4. Redirect to the new route.
- Rehash supported passwords on successful login.
- Force safe recovery for unsupported hashes.
- Do not preserve insecure message rendering, debug routes, GET mutations, or direct object references.
- Approve a shared-aggregate coexistence matrix before cohort routing. For accounts, people, passwords, profiles, training, reference masters, and documents it records:
  - Authoritative system by migration phase.
  - Read route and write route.
  - One-way synchronization direction.
  - Conflict and outage handling.
  - Final ownership cutover condition.

### Phase 2 — idempotent ETL

Migration order:

1. Reference and location masters.
2. Accounts, people, identifiers, addresses, emergency contacts.
3. Centers, course types, courses, sessions, teachers, documents.
4. Form definitions and immutable versions.
5. Applications, active drafts, immutable submissions, profile snapshots, manager details, and alumni-eligibility events.
6. Answers and answer snapshots.
7. Training experiences, legacy training summaries, and attendance plans.
8. Reviews, decisions, invitations, and action tokens.
9. Check-ins, facilities, and course-staff assignments.
10. Consent acceptances, notifications, exports, documents, and audits that can be lawfully retained.

Every destination row records source, `legacy_id`, and migration batch. Invalid records enter a quarantine table with reason. Nothing is silently dropped.

### Phase 3 — shadow validation

- Compare counts and checksums by course, center, person, category, state, teacher, and check-in.
- Replay every persona and conditional-form fixture.
- Diff applicant, teacher, laundry, print, and eight-sheet Excel outputs.
- Compare an approved status-membership matrix for every report screen, counter, print view, worksheet, and export. Resolve current contradictions rather than freezing them accidentally.
- Verify laundry gender/category segmentation, room, days `01`–`08`, laundry cost, purchase cost, and total.
- Compare email audience, template, attachment, map, and link matrices.
- Validate legacy links.
- Test card companion on supported Windows and macOS versions.
- Complete staff UAT with anonymized production-shape data.

### Phase 4 — cohort cutover

- Select one low-risk course session.
- Confirm the shared-aggregate coexistence matrix has one owner and one write path for every migration phase.
- Legacy remains source of truth until the cohort switch.
- Avoid bidirectional dual-write.
- Use a brief final write freeze.
- Run final delta, counts, and checksums.
- Switch routes for the cohort.
- Put legacy cohort views in read-only mode.
- Roll back by traffic switch only before new-system writes.
- After new writes, use tested forward recovery.

### Phase 5 — retirement

- Monitor compatibility URL usage.
- Export required audit records.
- Remove legacy token decoder after the approved active-link window.
- Remove password-hash compatibility after account transition criteria pass.
- Remove fixed question-ID translation after all historical rendering is verified.
- Retire public assets only after owner approval, retention review, and redirect telemetry.
- Destroy old PII according to approved retention policy.

## 14. Mandatory discovery and decision gates

| Gate | Required artifact | Owner | Exit condition |
|---|---|---|---|
| G0 Production truth | DDL, data profile, volume, anomalies, rejection/alumni/staff/training/consent evidence | Data lead | Reproducible legacy baseline |
| G1 Target schema | External schema plus this PRD reconciliation | Architecture/data | Every source field mapped |
| G2 Lifecycle | Canonical transition, alumni eligibility, invitation-decline, staff-applicant, and legacy-state matrix | Product/operations | `accepted`, rejection actor, cancellations, post-invitation semantics, alumni and staff rules resolved |
| G3 Compatibility | Route/link/document/report/admin-account ledger plus shared-aggregate coexistence matrix | Product/engineering | Every item has an outcome; every shared aggregate has one owner/write path per phase |
| G4 RBAC | Role/action/resource/sensitive-field matrix | Product/privacy | Approved least-privilege matrix |
| G5 Forms | Field/rule/persona/consent mapping | Product/operations | All variants replay successfully |
| G6 Parity | Golden reports, status-membership matrix, exact laundry fields, emails, PDFs, URLs | Operations | Signed output matrix |
| G7 Privacy | Legal basis, retention, deletion, support access | Privacy owner | Signed PDPA policy |
| G8 Infrastructure | Regional service matrix, residency, RPO/RTO, cost | Platform owner | Approved topology |
| G9 Cutover | Rehearsal, rollback, support plan | Delivery owner | Go/no-go signed |

Open product decisions from `Tasks.md`:

- `CLARIFY-001`: additional-fields popup target, source, and order.
- `CLARIFY-002`: Dhamma-worker grouping rule.
- `CLARIFY-003`: substance-use saved value and reload behavior.
- `CLARIFY-004`: Q082 wording, choices, groups, and migration.
- `CLARIFY-005`: “Email 2” audience, course mapping, copy, and assets.
- `CLARIFY-006`: SSL provider, access, renewal, and ownership.

## 15. Delivery roadmap

### Increment 1 — truth and safety

- Complete Gates G0–G5.
- Create reproducible legacy database fixture.
- Remove or isolate critical public debug exposure in the current system under a separate emergency change.
- Approve lifecycle, RBAC, and privacy policy.
- Produce route, document, report, and email compatibility ledgers.

### Increment 2 — platform foundation

- Bootstrap target stack and CI/CD.
- Implement accounts, people, RBAC, audit, reference data, and course catalog.
- Add deployment, backups, secrets, monitoring, and health.
- Implement compatibility routing and password/token bridges.

### Increment 3 — applicant lifecycle

- Implement `ApplicationWorkflow`.
- Implement `FormEngine`.
- Build all four guided variants and conditional branches.
- Add applicant timeline, autosave, receipts, cancellation, and confirmation.
- Migrate a representative anonymized dataset.

### Increment 4 — course operations

- Build Course Workspace and work queues.
- Add review, selection, bulk actions, invitations, communications, and outbox.
- Add check-in, manual verification, and hardened card companion.
- Add facilities, laundry, and course-staff conversion.

### Increment 5 — reports and parity

- Implement versioned applicant, teacher, laundry, print, and export specifications.
- Run golden comparisons.
- Migrate documents and compatibility URLs.
- Complete performance, accessibility, privacy, and security testing.

### Increment 6 — cohort migration

- Rehearse twice on production-shape snapshots.
- Cut over one cohort.
- Monitor, reconcile, and obtain operational acceptance.
- Expand by cohort.
- Retire legacy only after gate criteria pass.

## 16. Testing strategy

Test through deep-module interfaces. Replace shallow implementation tests after equivalent interface coverage exists.

Required suites:

- Lifecycle transition-table tests.
- Property tests proving invalid transitions never succeed.
- Invitation-decline versus reviewer-rejection classification tests.
- Legacy and target alumni-eligibility tests.
- Form-version immutability and answer-snapshot tests.
- Draft autosave, optimistic-lock conflict, transactional freeze, and superseding-submission tests.
- Application-submission revision and reviewed-revision tests.
- Per-course review independence, multi-reviewer round, superseding-review, reconsideration, and current-projection replay tests.
- Historical-review stability tests proving later profile, training, form-label, and answer changes cannot alter an earlier reviewed snapshot.
- Legacy review/status backfill tests proving unknown reviewer, note, score, and reason are not fabricated.
- Conditional visibility across every persona, course type, and applicant classification.
- Server/client rule-evaluator contract fixtures.
- Form publication checks for cycles, broken references, unreachable fields, missing translations, destructive choice changes, and report mappings.
- `QuestionSection` renderer coverage and accessibility tests for every question type.
- Legacy group/question/choice-to-semantic-key reconciliation fixtures.
- RBAC matrix tests by role, center, session, action, and sensitive field.
- Adapter contract tests for Postmark/Twilio, storage, card reader, and exports.
- Browser-to-companion and server assertion-verification tests, including device key rotation/revocation.
- Deterministic provider fakes.
- Migration reconciliation using anonymized production-shape fixtures.
- Legacy action-token and password-hash compatibility.
- PII log-redaction tests.
- CSRF, CORS, IDOR, XSS, open redirect, replay, rate limit, and formula injection.
- Thai search, Buddhist date, timezone, empty-string/`NULL`, and ordering fixtures.
- Golden XLSX, print, email, document URL, and attachment tests.
- Endpoint-ledger reconciliation proving all `W001-W099` and `A001-A010` retain a disposition.
- Page/artifact reconciliation proving all 91 Blade files, 8 root HTML artifacts, 2 root PHP artifacts, and 12 public PDFs retain a disposition.
- Flow-ledger scenarios covering loading, empty, validation, denied, expired/stale, partial external failure, interrupted retry, and success where applicable.
- Design-token schema, generated-adapter drift, forbidden raw-value, framework-import, selector-leakage, CSS-budget, accessibility, and visual-regression gates.
- Card companion on supported Windows and macOS, including manual fallback.
- WCAG 2.2 AA keyboard, focus, contrast, error, and screen-reader checks.
- Responsive checks at 320, 375, 768, 1024, and 1440 px.
- Load tests for registration opening, verification, bulk email, exports, and check-in arrival.
- Backup restore, failed deployment rollback, failed migration, and forward-recovery drills.

## 17. Product acceptance criteria

- Every verified current feature maps to a new feature, compatibility adapter, or approved retirement.
- Applicant-declined invitation remains distinct from reviewer rejection.
- Legacy alumni users retain the correct flow until an approved eligibility policy migrates them.
- All four guided variants and conditional branches complete without data loss.
- Applicant sees one canonical state and next action.
- Unauthorized transitions and sensitive reads are rejected and audited.
- Review decision and application state remain separate.
- Every course application retains its own review rounds, submitted reviews, contributing reviewers, reasons, decisions, and reviewed submission; another course cannot overwrite them.
- Historical review output remains unchanged after current profile, training, question wording, or answer edits.
- Existing latest-status screens retain parity through derived current projections.
- Active safe legacy links resolve during the approved window.
- Historical forms render from immutable versions and answer snapshots.
- Autosave survives interruption; submission atomically freezes the exact draft without mutating earlier submissions.
- Receipts and reviews reference an immutable submitted revision.
- Applicant, teacher, laundry, print, and eight-sheet export parity is signed.
- Every output uses its approved status-membership matrix; laundry fields match the approved field matrix.
- Communication audience, template, attachment, delivery, retry, and bounce behavior is visible.
- Bulk work is idempotent and reports partial failures.
- Card-assisted and manual check-in both work.
- No critical security-gate pattern remains.
- Migration counts/checksums reconcile; quarantined records have owner decisions.
- Shared accounts, people, profiles, training, masters, and documents have one authoritative writer per cohort phase.
- Public course discovery is crawlable and usable without a successful client-side boot.
- WCAG 2.2 AA and defined responsive sizes pass.
- Backup restore and cohort rollback are rehearsed.

## 18. Traceability matrix

| Current capability | Target module | Primary interface or screen | Proof |
|---|---|---|---|
| Course catalog/detail/filter/documents | Course Catalog & Sessions | Public catalog/detail | Crawl, filter, eligibility, URL tests |
| Registration/login/recovery/profile | Identity & Access; People & Profiles | Account screens | Auth, recovery, ownership tests |
| Four guided variants | Form Engine; Application Workflow | Applicant task flow | Persona fixture matrix |
| Legacy apply/accept | Legacy Compatibility; Application Workflow | Adapter/redirect ledger | Route contract tests |
| Status transitions | Application Workflow | `ApplicationWorkflow` | Transition/property tests |
| Review/selection and per-course history | Review & Selection | Course Workspace Review; customer course timeline | RBAC, reviewed-revision, review-round, supersession, and projection-replay scenarios |
| Invitation/confirmation/cancellation | Invitations & Confirmations | Applicant timeline; Communications | Link, outbox, delivery tests |
| Check-in and card reader | Check-in & Attendance | Course Workspace Check-in | Hardware/manual scenarios |
| Applicant report/eight sheets | Reports & Exports | Reports | Golden workbook diff |
| Teacher report/max-ten print | Reports & Exports | Teacher report/print | Golden print and rule tests |
| Laundry | Operations & Facilities; Reports & Exports | Operations/Laundry | UI/export fixtures |
| Email variants and attachments | Notifications; Documents & Consent | Communications | Audience/template matrix |
| PDFs/public URLs | Documents & Consent | Document library | URL/checksum smoke tests |
| Administrator accounts | Identity & Access | Access administration | Create/edit/deactivate/recover/scope/last-admin/audit scenarios |
| Scheduler commands | Notifications; Application Workflow | Worker/scheduler | Time/idempotency tests |
| Thai/Buddhist formatting | Design System locale implementation | All screens/exports | Locale golden tests |
| Page and flow preservation | BA/SA discovery package | Page register and flow ledger | 109-endpoint and 91-view reconciliation |
| Corporate Identity and CSS | Design System | Tokens, primitives, patterns, templates, web/email/print adapters | Token/build drift, a11y, responsive, visual, and leakage gates |

## 19. Explicit retirement safeguards

Do not retire without production-owner approval and usage evidence:

- Parole-related workflow.
- Placeholder dashboard, summary, or report routes.
- Legacy application and acceptance URLs.
- Dhamma-language data.
- Maximum-ten teacher print behavior.
- Public token cancellation contract.
- Any PDF or email variant.
- Bookmarked administrative routes.
- Historical read access.

Retirement proof requires route telemetry, data owner, retention decision, user communication, compatibility period, rollback plan, and signed acceptance.

## 20. Validator verdict

Feature validator:

- Initial architecture direction passed.
- Parity failed until the complete feature inventory, unsafe-route retirement rules, reports, documents, check-in, and migration gates were added.
- This PRD includes those amendments.

Architecture/integration validator:

- Laravel modular monolith is preferred.
- PostgreSQL, Redis/Horizon, provider adapters, object storage, audited exports, and hardened card companion are suitable.
- Live schema, operational environment, data volume, and provider metrics remain unverified.

UX/workflow validator:

- Lifecycle Workspace and cross-course queues fit the repository workflows.
- Implementation readiness remains conditional on live schema, active-link compatibility, RBAC, report parity, unresolved product decisions, and AWS regional service proof.

Final verdict:

**Product and architecture specification: pass. Production implementation start: conditional.**

Begin platform scaffolding only after G0–G5 have named owners and approved outputs. Begin data migration or feature retirement only after the applicable gates pass.

## 21. Primary references

Repository evidence:

- `routes/web.php`
- `routes/api.php`
- `app/Http/Controllers/ApplyController.php`
- `app/Http/Controllers/NewFlow/ApplyWizardController.php`
- `app/Business/GuidedFlowService.php`
- `resources/views/apply/v2/`
- `app/Http/Controllers/BackendApproveController.php`
- `app/Http/Controllers/BackendReportController.php`
- `app/Http/Controllers/BackendExportController.php`
- `app/Console/Commands/`
- `app/Services/TwilioVerifyService.php`
- `app/Helpers/Helper.php`
- `resources/views/backend/`
- `cardreader_service/_sourcecode/main.go`
- `db_scripts/`
- `Tasks.md`

Current official technology references:

- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases)
- [Laravel starter kits](https://laravel.com/index.php/starter-kits)
- [Inertia 3 documentation](https://inertiajs.com/docs/v3/getting-started)
- [PostgreSQL current documentation](https://www.postgresql.org/docs/current/index.htm)
- [AWS Regions and Availability Zones](https://docs.aws.amazon.com/global-infrastructure/latest/regions/aws-regions.html)
- [Postmark developer documentation](https://postmarkapp.com/developer)
- [Twilio Verify verification resource](https://www.twilio.com/docs/verify/api/verification)
- [PhpSpreadsheet](https://github.com/phpoffice/phpspreadsheet)
- [Laravel Horizon](https://laravel.com/docs/13.x/horizon)
