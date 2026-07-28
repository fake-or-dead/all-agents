# Tapoda Current-to-Target Module Blueprint

Date: 2026-07-28

Repository: `/Users/fake-or-dead/Sites/tapoda`

Observed branch / HEAD: `uat-20260526` / `3d2c3a4`

Purpose: system-architecture input for the replacement PRD. This is an evidence-backed ownership and migration design, not an implementation plan or a statement that the current code already has these module seams.

## 1. Architecture decision

Rebuild Tapoda as a modular monolith first. Keep one deployable application and one PostgreSQL cluster, but create explicit in-process module seams, one writer per aggregate, durable asynchronous work, and independent route-level frontend bundles.

This choice gives more leverage than immediate microservices:

- Tapoda's workflows share account, profile, course, application, consent, review, invitation, and check-in state.
- Current problems come mainly from shallow ownership and cross-cutting implementation, not from insufficient network boundaries.
- Transactional consistency is valuable during migration.
- A real module interface can later become a remote interface if scale or organizational ownership requires it.
- A fake implementation for each important external adapter makes the interface the test surface.

Target shape:

```text
HTTP / browser / command / worker adapters
                    |
              application modules
                    |
        explicit in-process module interfaces
                    |
       owned state + local-substitutable adapters
                    |
       true-external adapters and durable outbox
```

No controller, Blade/React page, report, mail template, job, or command may write another module's tables directly. A module may query another module only through its public interface or an explicitly published read projection.

## 2. Evidence baseline

### 2.1 Current application shape

- Framework: Laravel `8.83.23`; PHP constraint `^7.3|^8.0` in `composer.json`.
- Routes: 109 concrete HTTP verb routes observed across `routes/web.php` and `routes/api.php`.
- Controllers: 22 files under `app/Http/Controllers`.
- Business/service classes: 12 files under `app/Business` and `app/Services`.
- Models: 21 model files under `app/Models`.
- Views: 91 Blade files plus one view README under `resources/views`.
- Console commands: `app/Console/Commands/InviteAccept.php` and `RequestConfirm.php`.
- Automated tests: 10 `*Test.php` files plus 2 test support files, with substantial source-shape assertions and limited workflow-level persistence coverage.
- Migrations/data scripts: 6 Laravel migration files, 19 change/runbook SQL files under `db_scripts`, and 2 root reference SQL datasets. The repository migrations are not a trustworthy complete schema definition.

### 2.2 Concentration and shallow ownership

The largest current controllers combine routing, validation, persistence, state transition, presentation, reporting, and delivery:

- `app/Http/Controllers/ApplyController.php`: approximately 80 KB.
- `app/Http/Controllers/BackendReportController.php`: approximately 60 KB.
- `app/Http/Controllers/BackendApproveController.php`: approximately 52 KB.
- `app/Http/Controllers/BackendExportController.php`: approximately 44 KB.
- `app/Helpers/Helper.php`: more than 1,200 lines; email, Thai dates, counts, queries, token handling, status labels, classification, and formatting.

These are shallow modules: large surface, low locality, and little hidden implementation depth. The replacement needs deep modules: small, stable interfaces hiding state rules, provider behavior, transaction boundaries, retry policy, authorization, and persistence details.

### 2.3 Frontend and asset evidence

- Public pages load Bootstrap 5.1.3 while `public/css/style.css` embeds Bootstrap 4.3.1; backend AdminLTE uses Bootstrap 4.6.1 JavaScript and jQuery.
- `public/admin`: approximately 88 MB and 2,061 files.
- `public`: approximately 133 MB and 2,447 files.
- `public/plugins`: 194 files.
- `public/css/style.css`: 11,869 lines.
- `public/css/bootstrap.min.css`: 10,413 lines.
- `public/css/custom.css`: 184 lines.
- Across all Blade files, 72 contain either inline `style` attributes or `<style>` blocks: 656 inline `style` attributes and 59 `<style>` blocks.
- Excluding mail, 231 inline `style` attributes and 43 `<style>` blocks remain; 53 non-mail files contain page scripts or script pushes and there are 149 `<script>` tags.
- `public/css` plus `resources/views` contain 2,414 `!important` occurrences.
- 374 unique raw hex colors across custom CSS and views.
- Frequent framework classes include `row` 495, `form-control` 442, `card` 365, `modal` 172, `btn` 161, and `table` 63.
- `webpack.mix.js` references `resources/js/app.js` and `resources/css/app.css`; those source paths are absent.
- There is no JavaScript lockfile.
- Device-specific server rendering exists: `CustomAuthController` chooses `auth.mobile-signup`; `CourseController`, `MemberController`, and legacy application pages pass `is_mobile`.

### 2.4 Runtime and governance evidence

- No `.github` CI workflow is present. `.styleci.yml` is the only observed automated-style configuration.
- `docker-compose.yml` declares `build: .`, but no Dockerfile is present.
- `develop-docker-compose.yml` uses `bridgeasiath/phalcon4-php74-nginx` and MariaDB `10.5.5` with a hard-coded database password.
- Deployment is a manual SSH and Git workflow.
- `web-server.conf` disables access logs, caches static assets for 365 days, and allows a 3,600-second PHP timeout.
- `php.ini` enables `display_errors`.
- `.env.example` defaults to file cache, local file storage, sync queue, and file session.
- `config/queue.php` defaults to `sync`.
- `config/cache.php` defaults to `file`.
- `config/filesystems.php` defaults to `local`.
- `config/session.php` contains divergent long-lived settings including a 3,000-minute lifetime, `expire_on_close`, encryption, and non-secure-cookie behavior.
- `config/cors.php` permits wildcard methods, origins, and headers.
- `app/Http/Middleware/VerifyCsrfToken.php` exempts `'*'`.
- `app/Http/Middleware/BackendAuth.php` authorizes through the single check `role === admin`.
- The scheduler in `app/Console/Kernel.php` is disabled/commented.
- `InviteAccept` only appends to `newfile.txt`; its intended database work is commented.
- `RequestConfirm` selects one approved application, sends email synchronously, and can mark notification state after a false delivery result.

## 3. Required architecture vocabulary and rules

### 3.1 Module

A module is one owner for a cohesive capability and its state. Its public surface is an interface; its implementation, tables, provider choices, and internal policies remain private.

### 3.2 Interface

An interface is the test surface and the allowed seam. Prefer a few command/result operations over CRUD-shaped methods. An interface should expose intent, not storage.

Example:

```php
interface ApplicationWorkflow
{
    public function handle(ApplicationCommand $command): ApplicationResult;
    public function view(ApplicationId $id, Actor $actor): ApplicationView;
}
```

The implementation decides whether `SubmitApplication` may move a draft to submitted, which validation applies, which immutable snapshot is created, and which outbox events are recorded. Callers cannot set an arbitrary status.

### 3.3 Deep and shallow

- Deep module: small interface, substantial hidden implementation, strong invariants, high leverage.
- Shallow module: wide interface that mostly forwards storage or provider details.
- Target modules below must remain deep.
- A class renamed from `ApplyServices` to `ApplicationService` without changing ownership is still shallow.

### 3.4 Seam and adapter

- A seam is justified where a production implementation and a fake or alternate implementation are useful.
- An adapter translates a module-owned interface to a framework, database, provider, old schema, file format, or browser capability.
- Do not create speculative interfaces around every class.
- Cross-module calls use an in-process seam.
- PostgreSQL, Redis, and local object stores are local-substitutable adapters.
- Postmark, Twilio, and a browser-connected identity-card reader are true-external adapters.

### 3.5 Leverage and locality

- Put a rule where one change has high leverage without increasing the public interface.
- Keep validation, transition, persistence, audit, and event creation local to the owning module.
- Keep provider retry and idempotency local to the provider adapter.
- Keep display tokens and controlled variants local to the Design System.
- Reports & Exports consumes stable projections; it does not reimplement business rules.

## 4. Target module map

### 4.1 Business modules

1. Identity & Access
2. People & Profiles
3. Reference Data
4. Course Catalog & Sessions
5. Form Engine
6. Application Workflow
7. Review & Selection
8. Invitations & Confirmations
9. Check-in & Attendance
10. Operations & Facilities
11. Notifications
12. Reports & Exports
13. Documents & Consent
14. Audit & Compliance

### 4.2 Transitional and technical modules

15. Legacy Compatibility
16. Design System
17. Platform Operations

`Course Workspace` is a composed experience, not a state-owning module. It presents projections from Course Catalog & Sessions, Application Workflow, Review & Selection, Invitations & Confirmations, Check-in & Attendance, Operations & Facilities, and Reports & Exports.

## 5. Module contracts, implementation depth, state, and current inputs

### 5.1 Identity & Access

Owns:

- applicant and staff credentials;
- registration, login, logout, password reset, account status;
- OTP/email verification challenges;
- authentication sessions and authorization roles/capabilities;
- account-to-person linkage.

Public interface:

```text
authenticate(AuthenticationCommand) -> AuthenticationResult
register(RegistrationCommand) -> AccountResult
recover(RecoveryCommand) -> RecoveryResult
authorize(Actor, Capability, ResourceRef) -> AuthorizationDecision
```

Hidden implementation depth:

- rate limiting, challenge expiry, replay prevention;
- password hashing/rehash and legacy verifier;
- role/capability evaluation;
- account lifecycle;
- security event creation;
- Twilio/Postmark verification choice;
- session invalidation.

Current inputs:

- `CustomAuthController`
- `SignupController`
- `BackendUserController`
- backend login methods in `AdminController`
- `TwilioVerifyService`
- `VerifiedEmailTokenManager`
- `auth/*`, `backend/auth/login`, `backend/user/*`

State:

- current `users`, `password_resets`, `personal_access_tokens`;
- target `accounts`, `credentials`, `verification_challenges`, `auth_sessions`, `roles`, `capabilities`, `account_roles`.

Adapters:

- Laravel password/session compatibility adapter;
- Twilio Verify adapter plus deterministic fake;
- Postmark verification adapter plus fake;
- legacy account/table reader during migration.

### 5.2 People & Profiles

Owns:

- the person's current editable profile;
- contact channels and addresses;
- training history;
- applicant identity and demographic details;
- profile completeness.

Public interface:

```text
profileFor(PersonId, Actor) -> ProfileView
update(ProfileCommand) -> ProfileResult
snapshot(PersonId, SnapshotPolicy) -> PersonSnapshot
```

Hidden implementation depth:

- field authorization and sensitive-field masking;
- Thai address/reference validation;
- profile completeness;
- training-history normalization;
- snapshot generation for an application.

Current inputs:

- `MemberController`
- `UserServices`
- profile-related logic in `ApplyController`
- `member/index`
- `contact`, `training_history_info`, and profile columns currently mixed into `users`

State:

- target `people`, `person_contacts`, `person_addresses`, `person_training_history`;
- Identity & Access owns the account; People & Profiles owns the person;
- Application Workflow owns immutable application snapshots and must not read mutable profile fields when reviewing an old submission.

### 5.3 Reference Data

Owns:

- geography;
- prefixes;
- languages;
- education levels;
- trainee/tutor types;
- configurable period/reference choices.

Public interface:

```text
list(ReferenceQuery) -> ReferenceCollection
resolve(ReferenceKey) -> ReferenceItem
```

Hidden implementation depth:

- activation/effective dates;
- display order;
- Thai/English labels;
- referential validation;
- caching.

Current inputs:

- `/select/amphoes`
- `/select/tambons`
- `ApplySelectServices`
- raw reference lookups spread across controllers, helpers, and forms

State:

- `amphoes`, `tambons`, `provinces`, `countries`, `prefixes`, `languages`,
  `education_level`, `trainee_type`, `tutor_type`, `custom_period_times`.

This is a read-heavy deep module. Consumers receive stable keys and labels, not table models.

### 5.4 Course Catalog & Sessions

Owns:

- course definitions and types;
- centers;
- scheduled course sessions/cohorts;
- teachers/facilitators;
- registration windows;
- capacity and enrollment policy;
- public discoverability.

Public interface:

```text
search(CourseSearch) -> CourseSearchResult
session(SessionId, Actor) -> CourseSessionView
configure(CourseCommand) -> CourseResult
registrationPolicy(SessionId, Instant) -> RegistrationDecision
```

Hidden implementation depth:

- date/window rules;
- capacity policy;
- public/staff projections;
- teacher assignment;
- center/course-type reference;
- search indexing and caching.

Current inputs:

- `CourseController@index`
- `CourseController@detail`
- `CourseFilterServices`
- `CourseServices`
- course-management methods in `BackendCourseController`
- `course/list`, `course/detail`, `course/course-info`, `course/course-info-v2`
- `backend/course/*`

State:

- `center`, `course`, `course_type`, `teacher`;
- target normalized `course_definitions`, `course_sessions`, `session_teachers`, `registration_policies`.

Course Catalog & Sessions does not approve an applicant or send an invitation. It supplies policy and course/session facts to the owning modules.

### 5.5 Form Engine

Owns:

- versioned form definitions;
- form-version drafting, preview, validation, and publication;
- sections, questions, choice sets, conditions, validation rules;
- answer validation;
- a frozen form schema for each submission version.

Public interface:

```text
schemaFor(FormContext) -> FormSchema
validate(FormSchemaVersion, AnswerSet) -> ValidationResult
freeze(FormSchemaVersion, AnswerSet) -> FrozenAnswers
handle(FormDefinitionCommand) -> FormDefinitionResult
```

Hidden implementation depth:

- conditional visibility;
- required-field calculation;
- type coercion;
- stable semantic question keys;
- version resolution;
- migration from numeric question IDs;
- answer serialization.
- publication checks, approval, and immutable version creation.

Current inputs:

- `ApplyQuestionServices`
- `SaveAcceptServices`
- question rendering/persistence inside `ApplyController`, `SaveController`, `AcceptController`, and `SaveAcceptController`
- `group_question`, `question`, `question_choices`, `question_has_group`, `question_apply_course`

State:

- target `form_definitions`, `form_version_drafts`, immutable `form_versions`, `form_sections`, `form_fields`, `form_options`, and `form_publication_events`;
- the Form Engine owns definitions;
- Application Workflow owns draft answers and immutable submitted answers.

### 5.6 Application Workflow

Owns:

- application identity and lifecycle;
- mutable draft;
- step completion;
- eligibility result;
- immutable submissions and snapshots;
- withdrawal/cancellation request;
- workflow transitions and lifecycle history.

Public interface:

```text
start(StartApplication) -> ApplicationResult
saveDraft(SaveDraft) -> ApplicationResult
submit(SubmitApplication) -> ApplicationResult
handle(ApplicationCommand) -> ApplicationResult
view(ApplicationId, Actor) -> ApplicationView
```

Hidden implementation depth:

- one active-application policy;
- registration-window validation;
- step readiness;
- Form Engine validation;
- immutable snapshot creation;
- state machine;
- idempotency;
- transaction plus outbox event;
- optimistic concurrency.

Current inputs:

- `ApplyController`
- `GuidedFlowService`
- `ApplyServices`
- `ProfileSyncService`
- `apply/v2/*`
- legacy `apply/*` through the compatibility seam
- application portions of `member/index`
- application-status mutations in `BackendCourseController`

State:

- current `apply_course`, `apply_course_manager`;
- target:
  - `applications` as mutable workflow envelope;
  - `application_drafts`;
  - `application_draft_answers`;
  - typed draft profile/manager/attendance records where schema requires them;
  - `application_submissions`;
  - immutable submission profile snapshots;
  - immutable submission answers;
  - submission references to immutable consent receipts owned by Documents & Consent;
  - `application_status_events`.

The state-machine implementation chooses transitions. No caller receives `setStatus(status)`.

### 5.7 Review & Selection

Owns:

- review queues;
- per-course review rounds bound to an exact application submission;
- reviewer assignment;
- mutable private review drafts;
- immutable submitted reviews, criteria, scores, and classified notes;
- decision records;
- decision authorization and conflict rules.

Public interface:

```text
assign(ReviewAssignmentCommand) -> ReviewResult
saveDraft(ReviewDraftCommand) -> ReviewResult
submit(SubmitReviewCommand) -> ReviewResult
decide(DecisionCommand) -> DecisionResult
queue(ReviewQueueQuery) -> ReviewQueue
```

Hidden implementation depth:

- reviewer eligibility;
- immutable-submission binding;
- review-round creation, reconsideration, and supersession;
- concurrent decision control;
- maker/checker or dual-control rules;
- reason requirements;
- bulk-action validation;
- audit append.

Current inputs:

- `BackendApproveController`
- `backend/approve/index`
- `backend/approve/course`
- `backend/approve/course-staff`
- `backend/approve/applicant`

State:

- target `review_rounds`, `review_assignments`, `review_drafts`, immutable `reviews`,
  `review_score_dimensions`, `review_scores`, `decisions`, and `decision_events`.

A decision references an exact `application_submission_id`. Review & Selection asks Application Workflow to apply an authorized transition; it does not update application status directly.
Course A and Course B review history never overwrite each other. A submitted review cannot change; correction creates a superseding review and reconsideration opens another review round.

### 5.8 Invitations & Confirmations

Owns:

- invitation/confirmation request;
- secure action token;
- delivery-independent response state;
- accept, decline, expire, withdraw, cancel, and recovery behavior;
- commitment/attendance confirmation.

Public interface:

```text
invite(InvitationCommand) -> InvitationResult
respond(InvitationResponseCommand) -> InvitationResult
withdraw(WithdrawalCommand) -> InvitationResult
resolveAction(ActionToken) -> ActionContext
```

Hidden implementation depth:

- token hashing and expiry;
- replay/idempotency;
- channel-independent invitation state;
- late-response and capacity rules;
- secure public action outcomes;
- outbox creation.

Current inputs:

- `/course/confirm`
- course cancel/canceled routes
- `AcceptController`
- `SaveAcceptController`
- invitation/confirmation methods in `CourseController` and `BackendCourseController`
- `accept/*`
- current `invite_accept`
- staging/manual `apply_course_confirm`

State:

- target `invitations`, `invitation_actions`, `confirmation_responses`, `withdrawals`.

Notification delivery does not own confirmation state. A delivery failure is retryable without changing the applicant's response state.

### 5.9 Check-in & Attendance

Owns:

- check-in access and course-scoped operator sessions;
- attendee lookup/readiness;
- identity verification result;
- arrival/check-in events;
- attendance projection.

Public interface:

```text
prepare(CheckinQuery) -> CheckinReadiness
verify(IdentityEvidence) -> IdentityResult
checkIn(CheckinCommand) -> CheckinResult
attendance(AttendanceQuery) -> AttendanceView
```

Hidden implementation depth:

- eligibility and confirmation checks;
- duplicate/replay prevention;
- identity evidence policy;
- course/operator authorization;
- offline/retry semantics if required;
- audit event creation.

Current inputs:

- `BackendCheckinController`
- `ExternalCheckinAuthController`
- `CheckinAccess` middleware
- API check-in search route
- `backend/checkin/course`
- `backend/checkin/form`
- `backend/auth/checkin-staff-login`
- browser TypeScript/local identity-card reader integration

State:

- current `checkins`;
- target `checkin_events`, `identity_verifications`, `attendance_records`.

The browser card reader is an adapter. It may provide evidence, but it cannot decide workflow eligibility.

### 5.10 Operations & Facilities

Owns:

- operational requests attached to confirmed attendees;
- laundry/uniform sizing;
- dinner, seating, accessibility, accommodation, and facility needs;
- fulfillment status.

Public interface:

```text
recordRequest(OperationsCommand) -> OperationsResult
fulfill(FulfillmentCommand) -> OperationsResult
worklist(OperationsQuery) -> OperationsWorklist
```

Hidden implementation depth:

- permitted request windows;
- sensitive accommodation access;
- fulfillment lifecycle;
- aggregate counts;
- export-safe projection.

Current inputs:

- laundry methods and screens in `BackendCourseController`
- laundry export logic in `BackendExportController`
- facility/dinner/seating values currently embedded in questions, application fields, or check-in data

State:

- target `room_assignments`, `facility_requests`, `participant_service_entries`,
  `participant_purchases`, and `operation_cost_adjustments`.

### 5.11 Notifications

Owns:

- notification intent;
- recipient/channel resolution;
- template version;
- outbox dispatch;
- provider attempts, retry, delivery status, and suppression.

Public interface:

```text
request(NotificationRequest) -> NotificationReceipt
status(NotificationId) -> NotificationStatus
```

Hidden implementation depth:

- provider choice;
- idempotency key;
- retry/backoff/dead-letter;
- recipient normalization;
- template rendering;
- localized subject/content;
- delivery telemetry;
- PII-safe logs.

Current inputs:

- `EmailServices`
- email operations in `Helper.php`, controllers, and commands
- all `resources/views/mail/*`
- `RequestConfirm`

State:

- target `notification_requests`, `notification_attempts`, `notification_suppressions`, transactional `outbox_events`.

Provider gateway interfaces:

```text
MailGateway.send(RenderedMessage) -> ProviderReceipt
OtpGateway.start/verify(...) -> VerificationResult
```

Production and fake implementations make these real seams. Notification request creation occurs in the business transaction; provider calls occur in workers.

### 5.12 Reports & Exports

Owns:

- versioned report definitions;
- report authorization;
- projection queries;
- asynchronous generation;
- XLSX/CSV/PDF/print rendering;
- artifact retention and download.

Public interface:

```text
request(ReportRequest) -> ReportJob
status(ReportJobId) -> ReportStatus
download(ReportArtifactId, Actor) -> DownloadGrant
```

Hidden implementation depth:

- projection schema;
- Thai date/label formatting;
- column definitions;
- spreadsheet/PDF renderer choice;
- row and memory limits;
- background execution;
- artifact hashing/retention.

Current inputs:

- `BackendReportController`
- `BackendExportController`
- `BackendSummaryController`
- `backend/report/*`
- `backend/summary`
- teacher/application/print views

State:

- target `report_jobs`, `report_artifacts`, versioned read projections/materialized views.

Reports & Exports never joins arbitrary private module tables at runtime. Each state-owning module publishes a stable projection or event-fed reporting model.

### 5.13 Documents & Consent

Owns:

- document metadata/version;
- secure file access;
- public course attachment policy;
- consent text/version;
- consent acceptance evidence.

Public interface:

```text
publish(DocumentCommand) -> DocumentResult
resolve(DocumentRef, Actor) -> DocumentGrant
recordConsent(ConsentCommand) -> ConsentReceipt
```

Hidden implementation depth:

- object-key generation;
- MIME/size/malware policy;
- signed URL duration;
- consent version binding;
- hash/evidence retention;
- public/private classification.

Current inputs:

- public PDFs, maps, and course attachments;
- `apply/v2/pdpa`;
- `apply/agreement`;
- `auth/agreement`;
- `accept/consent-form`.

State:

- target `documents`, `document_versions`, `consent_definitions`, `consent_versions`, `consent_acceptances`.

### 5.14 Audit & Compliance

Owns:

- append-only security and business audit events;
- sensitive-data access records;
- retention/legal-hold policy;
- compliance query.

Public interface:

```text
append(AuditEvent) -> AuditReceipt
query(AuditQuery, Actor) -> AuditPage
```

Hidden implementation depth:

- actor/request correlation;
- before/after redaction;
- tamper evidence;
- retention enforcement;
- privilege checks.

Every module appends a semantic event through this interface. The audit implementation must not become a generic synchronous dumping ground.

### 5.15 Legacy Compatibility

Owns only temporary translation:

- old routes and redirects;
- old action tokens;
- old numeric IDs and field/status values;
- old password hashes during controlled rehash;
- read adapters for old tables;
- migration quarantine and reconciliation.

Public interface:

```text
resolveLegacyRoute(LegacyRequest) -> CanonicalRoute
exchangeLegacyToken(LegacyToken) -> CanonicalAction
mapLegacyRecord(LegacyRecord) -> MigrationResult
```

Current inputs:

- `AcceptController`
- `SaveAcceptController`
- `SaveController`
- legacy portions of `ApplyController`
- `MessageController`
- `BackendUserParoleController`
- old `/accept/*`, `/course/apply-training-detail`, `/course/save-apply`,
  `/course/save-course-history`, `/course/apply-question`, `/course/agreement`
- `apply` legacy table

Rules:

- No new feature may depend on Legacy Compatibility.
- Every adapter has an owner, usage telemetry, removal criterion, and deadline.
- `/messages/{message}` becomes a typed outcome code adapter; never render an arbitrary message parameter.
- `NewFlow/ApplyWizardController` references absent `apply-new/*` views and has no active route. Treat it as an orphan prototype: preserve evidence, then remove unless an owner validates hidden use.
- `/welcome`, `/test`, `/xxxxx`, `/mail`, and placeholder API routes are development/compatibility inventory, not production requirements.

### 5.16 Design System

Owns:

- design tokens;
- UI primitives, patterns, templates;
- controlled variants;
- accessibility behavior;
- responsive behavior;
- print and email visual foundations;
- visual-regression fixtures.

Public interface:

- package exports for tokens, typed variants, primitives, patterns, templates, and page schemas;
- no business state.

Implementation depth:

- accessible interaction;
- semantic tokens;
- focus/error behavior;
- responsive field/action schemas;
- route-level asset isolation;
- visual baselines.

### 5.17 Platform Operations

Owns technical runtime mechanisms:

- queue, scheduler, locks, cache, session, health/readiness;
- observability;
- migration execution;
- secrets and configuration delivery;
- backup/restore;
- build and deployment.

It does not own business status. Business modules call abstract job, clock, lock, transaction, and outbox mechanisms only where a true seam improves tests or portability.

State:

- `failed_jobs`, framework `migrations`;
- target `jobs`, `failed_jobs`, `cache`, `cache_locks`, `sessions`, deployment metadata.

## 6. Current page and route ownership

Every current page has exactly one target owner. A page may compose read-only projections from other modules, but its route, authorization, task completion, analytics, and primary UX owner remain singular.

### 6.1 Public pages

| Current route/view | Current entry | Target owner | Target treatment |
|---|---|---|---|
| `/`, `/course`; `home`, `course/list` | `CourseController@index` | Course Catalog & Sessions | Unified searchable catalog; retire duplicate home/list markup |
| `/course/detail/{course_code}`; `course/detail` | `CourseController@detail` | Course Catalog & Sessions | Session detail with registration decision from the module interface |
| `course/course-info`, `course/course-info-v2` | shared partials | Course Catalog & Sessions | One versioned course-detail pattern |
| `/about`; `about` | route closure/controller | Documents & Consent | Managed public content/document page |
| `/applicant-qualifications`; `qualification` | route closure/controller | Documents & Consent | Versioned qualification content |
| `/suggest`; `suggest` | route closure/controller | Documents & Consent | Managed recommendation/help content |
| `welcome` | development/legacy route | Legacy Compatibility | Retire unless a named production journey is proven |

### 6.2 Authentication and account pages

| Current route/view | Current entry | Target owner | Target treatment |
|---|---|---|---|
| `/signup`; `auth/signup`, `auth/mobile-signup` | `CustomAuthController` / signup logic | Identity & Access | One responsive registration page; no user-agent fork |
| `/signin`, `/signout` | `CustomAuthController` | Identity & Access | Applicant authentication |
| `/agreement`; `auth/agreement` | auth flow | Identity & Access | Identity-owned task; consent definition supplied by Documents & Consent |
| `/forgot`, `/forgot-password`; `auth/forgot` | auth flow | Identity & Access | Unified recovery |
| `/backend/login`, `/backend/signin`, `/backend/logout`; `backend/auth/login` | `AdminController` | Identity & Access | Staff authentication and capability authorization |
| `/backend/user*`; `backend/user/index`, `backend/user/form` | `BackendUserController` | Identity & Access | Account and access administration |

API ownership:

- signup OTP request/verify/signup: Identity & Access;
- citizen/username existence/readiness: Identity & Access, rate-limited and enumeration-safe;
- Sanctum `/user`: Identity & Access;
- `/select/amphoes`, `/select/tambons`: Reference Data.

### 6.3 Applicant workflow pages

| Current route/view | Target owner | Target treatment |
|---|---|---|
| `/course/apply/{token}` | Legacy Compatibility | Resolve/exchange old token, then redirect to canonical application task |
| `apply/v2/partials/status-alert` | Application Workflow | Application status/task summary |
| `apply/v2/profile` | Application Workflow | Draft profile snapshot task using People projection |
| `apply/v2/training-history` | Application Workflow | Draft training task using People history |
| `apply/v2/preferences` | Application Workflow | Application-owned preferences |
| `apply/v2/teacher-details` | Application Workflow | Conditional role-specific draft task |
| `apply/v2/commitment` | Application Workflow | Commitment task; consent text supplied by Documents & Consent |
| `apply/v2/management-details` | Application Workflow | Conditional manager draft task |
| `apply/v2/pdpa` | Application Workflow | Submission task recording a Documents & Consent version |
| `apply/v2/_progress`, `_action_styles` | Design System | Replace with `FormStep`, `Progress`, and action primitives |
| `apply/v2/README` | Application Workflow | Preserve as implementation evidence; replace with executable documentation/tests |
| `apply/agreement`, `apply/course-history`, `apply/question`, `apply/user-detail` | Legacy Compatibility | Route adapters into canonical Application Workflow/Form Engine tasks |
| `/course/apply-training-detail`, save/apply/history/question/agreement routes | Legacy Compatibility | Preserve contracts temporarily, then retire by telemetry |

The primary owner is Application Workflow even when a step displays People data, Form Engine schema, or Documents & Consent text. Application Workflow owns task readiness and completion.

### 6.4 Acceptance, confirmation, and outcome pages

| Current route/view | Target owner | Target treatment |
|---|---|---|
| `/course/confirm`, cancel/canceled URLs | Invitations & Confirmations | One secure action flow with typed outcomes |
| `accept/consent-form`, `course-history`, `more-info`, `question`, `user-detail`, `update-reminder` | Legacy Compatibility | Translate to Invitations/Application/Form tasks; remove duplicate persistence |
| `message/message`, `message/accept-success`, `layouts/message` | Invitations & Confirmations | Typed action-result page; legacy route handled by adapter |
| `preview/accept-reminder` | Notifications | Template preview fixture, disabled in production |

### 6.5 Member page

| Current route/view | Target owner | Target treatment |
|---|---|---|
| `/member/info`; `member/index` | People & Profiles | Profile hub with read-only Application Workflow history projection |
| member update-profile/training-history actions | People & Profiles | Explicit profile commands |
| member password action | Identity & Access | Link/task owned by Identity; not processed by People |

### 6.6 Staff workspace pages

| Current view/route family | Target owner | Target treatment |
|---|---|---|
| `/backend` staff landing | Design System | Course Workspace shell/navigation composed from authorized module projections |
| `backend/approve/index`, `course`, `course-staff`, `applicant` | Review & Selection | Review queue/detail/decision patterns |
| `backend/course/index`, `backend/course/manage` | Course Catalog & Sessions | Course/session configuration; composed workspace links to other modules |
| `backend/course/laundry` | Operations & Facilities | Laundry/facility worklist for one course/session |
| `backend/checkin/course`, `form` | Check-in & Attendance | Readiness and check-in task |
| `backend/auth/checkin-staff-login` | Check-in & Attendance | Course-scoped operator access; Identity authenticates actor |
| `backend/report/apply-course`, `index`, `table`, partials, `teacher`, teacher modal/print, draft | Reports & Exports | Versioned report definitions and print/export views |
| `backend/summary` | Reports & Exports | Published course-workspace summary projection |
| `backend/parole/*` | Legacy Compatibility | Unresolved legacy function; require product owner before migration |
| `backend/layouts/*`, `backend/includes/*`, `backend/blank` | Design System | Replace with workspace templates/primitives |
| public `layouts/*`, `includes/*` | Design System | Replace with Public/Applicant/Auth templates |
| `components/course-history/part-time-warning` | Application Workflow | Conditional application-task warning; visual treatment supplied by Design System |
| `components/accept/update-reminder` | Invitations & Confirmations | Reminder/action pattern; visual treatment supplied by Design System |

`BackendCourseController` is split by intent:

- course/session configuration -> Course Catalog & Sessions;
- application status mutation -> Application Workflow;
- request confirmation -> Invitations & Confirmations;
- check-in password/access -> Check-in & Attendance;
- laundry/facility work -> Operations & Facilities.

### 6.7 Mail, print, and static artifacts

| Current artifact | Target owner |
|---|---|
| `mail/accept-d03`, `accept-d10`, `accept-staff` | Notifications |
| `mail/cancel` | Notifications |
| `mail/confirmed-monastic-d10`, `confirmed-staff-all`, `confirmed-trainee-d03`, `confirmed-trainee-d10` | Notifications |
| `mail/invite`, `invite-staff` | Notifications |
| `mail/request-confirm` | Notifications |
| `mail/reset`, `success`, `welcome` | Notifications |
| `backend/report/*print*` | Reports & Exports |
| public PDFs/maps/course attachments | Documents & Consent |

Email templates use `EmailShell` and email-safe semantic token values. They must remain versioned because historical content can be compliance evidence.

### 6.8 Development, diagnostic, and placeholder routes

| Current route | Single target owner | Target treatment |
|---|---|---|
| `/test`, `/xxxxx/{uid}`, API `/test` | Legacy Compatibility | Remove from production; preserve only named automated fixtures |
| `/mail`, `/_local/mail-preview/{type?}` | Notifications | Local/test-only template preview behind environment and authorization checks |
| `/preview/accept-reminder/{apply_token?}` | Notifications | Local fixture; never expose applicant data in production preview |
| API `/apply-stat/{course_code}` | Reports & Exports | Treat as placeholder; replace only if a named consumer and report contract exist |
| API `/checkins/search/{course_code}/{personal_id}` | Check-in & Attendance | Authenticated, course-scoped query with enumeration/rate controls |
| `/messages/{message}` | Legacy Compatibility | Map allowed legacy values to typed Invitations outcomes; reject arbitrary content |
| `/message-accept-success` | Invitations & Confirmations | Canonical typed success outcome |
| `/backend/export` | Reports & Exports | Report/export request catalog, not direct controller-generated files |

## 7. Current controller ownership

| Current controller | Target owner / split |
|---|---|
| `AcceptController` | Legacy Compatibility -> Invitations & Confirmations, Application Workflow, Form Engine |
| `SaveAcceptController` | Legacy Compatibility -> Form Engine/Application Workflow |
| `AdminController` | Identity & Access plus Course Workspace route adapters |
| `SignupController` | Identity & Access |
| `ApplyController` | Application Workflow; legacy actions through Legacy Compatibility |
| `BackendApproveController` | Review & Selection |
| `BackendCheckinController` | Check-in & Attendance |
| `BackendCourseController` | Split by explicit intents listed in section 6.6 |
| `BackendExportController` | Reports & Exports; laundry projection from Operations & Facilities |
| `BackendReportController` | Reports & Exports |
| `BackendSummaryController` | Reports & Exports projection presented in Course Workspace |
| `BackendUserController` | Identity & Access |
| `BackendUserParoleController` | Legacy Compatibility until requirements are recovered |
| `CourseController` | Course Catalog & Sessions; invitation action links delegated |
| `CustomAuthController` | Identity & Access |
| `ExternalCheckinAuthController` | Check-in & Attendance access adapter |
| `MemberController` | People & Profiles |
| `MessageController` | Legacy Compatibility -> typed Invitations outcome |
| `NewFlow/ApplyWizardController` | Orphan prototype; validate then retire |
| `SaveController` | Legacy Compatibility |
| `TestController` | Development-only; remove from production |
| base `Controller` | Framework HTTP adapter base; no business ownership or state |

Target controllers are thin adapters: parse transport, build a command/query, call one owning module interface, and map the result. They contain no query builder, status transition, mail send, date policy, or report logic.

## 8. Current business/service ownership

| Current class/file | Target owner | Required change |
|---|---|---|
| `ApplyQuestionServices` | Form Engine | Replace numeric-ID behavior with versioned semantic schema |
| `ApplySelectServices` | Reference Data | Return stable reference DTOs |
| `ApplyServices` | Application Workflow | Remove redirects/view formatting; accept intent commands |
| `CourseFilterServices` | Course Catalog & Sessions | Hide search/filter implementation behind `search` |
| `CourseServices` | Course Catalog & Sessions | Merge rules into deep module implementation |
| `EmailServices` | Notifications | Replace direct send with durable request/outbox |
| `GuidedFlowService` | Application Workflow | Become internal step/readiness implementation |
| `ProfileSyncService` | People & Profiles / Application Workflow seam | Replace sync mutation with People projection + immutable Workflow snapshot |
| `SaveAcceptServices` | Legacy Compatibility/Form Engine | Temporary adapter only |
| `UserServices` | People & Profiles | Separate account concerns into Identity & Access |
| `TwilioVerifyService` | Identity & Access adapter | Implement `OtpGateway`; add fake and provider-contract tests |
| `VerifiedEmailTokenManager` | Identity & Access | Token lifecycle inside module implementation |
| `Helper.php` | No owner as a whole | Dissolve each function into its owning module or Design System locale implementation |

Representative `Helper.php` migration:

- token encode/decode -> Identity & Access or Legacy Compatibility;
- application status text -> Application Workflow projection;
- email send -> Notifications;
- Thai date formatting -> Design System locale implementation / Reports & Exports renderer;
- backend authorization -> Identity & Access;
- database counts/queries -> owning module projection;
- course classification -> Course Catalog & Sessions;
- consent/document URL -> Documents & Consent.

Do not create a replacement `CommonService`, `Utils`, or `Helpers` module.

## 9. Current and target data ownership

The exact live DDL must be captured before implementation. The following owner map is based on models, migrations, scripts, and query evidence in this repository.

| Current table | Sole target owner | Target disposition |
|---|---|---|
| `users` | Identity & Access | Account/credential fields migrate to Identity; profile fields ETL to People |
| `password_resets` | Identity & Access | Replace with expiring recovery challenges |
| `personal_access_tokens` | Identity & Access | Preserve only if required; issue new scoped tokens |
| `contact` | People & Profiles | Normalize contact/address records |
| `training_history_info` | People & Profiles | Normalize person training history |
| `amphoes` | Reference Data | Keep as versioned geography reference |
| `tambons` | Reference Data | Keep as versioned geography reference |
| `provinces` | Reference Data | Keep as versioned geography reference |
| `countries` | Reference Data | Keep as versioned geography reference |
| `prefixes` | Reference Data | Stable key + localized label |
| `languages` | Reference Data | Stable key + localized label |
| `education_level` | Reference Data | Rename/normalize |
| `trainee_type` | Reference Data | Stable semantic type |
| `tutor_type` | Reference Data | Stable semantic type consumed by Course/Form |
| `custom_period_times` | Reference Data | Validate owner/use; retain as configurable reference if active |
| `center` | Course Catalog & Sessions | Normalize center/site |
| `course` | Course Catalog & Sessions | Split definition from scheduled session if mixed |
| `course_type` | Course Catalog & Sessions | Stable course type |
| `teacher` | Course Catalog & Sessions | Course facilitator assignment; link to person/account only through IDs |
| `group_question` | Form Engine | Versioned section |
| `question` | Form Engine | Versioned semantic field |
| `question_choices` | Form Engine | Versioned option |
| `question_has_group` | Form Engine | Versioned schema relation |
| `question_apply_course` | Application Workflow | Migrate answer value to draft/submission answer keyed by Form Engine semantic key |
| `apply_course` | Application Workflow | Migrate to application envelope/draft/submission |
| `apply_course_manager` | Application Workflow | Migrate to typed draft/submission snapshot |
| `apply_course_confirm` | Invitations & Confirmations | Validate live DDL; migrate confirmation event/state |
| `invite_accept` | Invitations & Confirmations | Migrate invitation/action/response |
| `checkins` | Check-in & Attendance | Migrate to append-only check-in event + attendance projection |
| `apply` | Legacy Compatibility | Read-only during migration; reconcile and retire |
| `failed_jobs` | Platform Operations | Retain with queue policy and monitoring |
| `migrations` | Platform Operations | Framework metadata; new baseline migration required |
| future `jobs`, `cache`, `cache_locks`, `sessions` | Platform Operations | Runtime-owned infrastructure state |

Target write rules:

- Identity writes account and credential tables only.
- People writes current person/profile tables only.
- Workflow writes application draft/submission/transition tables only.
- Form Engine writes definition/version tables only.
- Review writes review records only.
- Invitations writes invitation/action/response tables only.
- Check-in writes verification/check-in/attendance tables only.
- Notifications writes request/attempt/suppression tables only.
- Reports writes jobs/artifacts/read models only.
- Documents writes document/consent tables only.
- Audit appends audit records only.

Cross-module database foreign keys may enforce stable identifiers inside one database, but another module's ORM models remain private. Use identifier value objects and public interfaces.

## 10. State ownership and lifecycle

### 10.1 Application lifecycle

Canonical high-level states:

```text
draft
  -> submitted
  -> under_review
  -> selected | not_selected | waitlisted
  -> invited
  -> confirmed | declined | invitation_expired
  -> checked_in
  -> completed

Any allowed pre-course state -> withdrawn/canceled, subject to policy
```

Exact state vocabulary must be reconciled against live values before implementation. `config/app.php` labels and `config/apply.php` messages are presentation evidence, not a safe state-machine definition.

Ownership:

- Application Workflow owns the canonical application state.
- Review owns review/decision state, then requests a Workflow transition.
- Invitations owns invitation/response state, then requests a Workflow transition.
- Check-in owns check-in event/state, then requests a Workflow transition.
- Notifications owns delivery state only.
- Operations owns facility-fulfillment state only.

Each accepted command writes:

1. owning aggregate change;
2. append-only transition/history;
3. audit event reference;
4. outbox event;

in one database transaction. Workers consume outbox events idempotently.

### 10.2 Draft, profile, and submission ownership

- People owns the mutable current profile.
- Application Workflow may initialize a draft from a People projection.
- Editing an application draft does not silently mutate the People profile.
- An explicit “update my profile too” command may call People through its interface.
- On submission, Workflow creates an immutable person/application snapshot.
- Review and Reports use the immutable submission, not the person's current profile.
- Form Engine owns schema versions; Workflow freezes the exact schema version and submitted answers.

### 10.3 Course policy ownership

- Course Catalog owns registration window, capacity, and session facts.
- Application Workflow asks `registrationPolicy` during start/submit.
- Review cannot change course capacity.
- Invitations asks Course policy before issuing or accepting where capacity matters.

### 10.4 Read projections

Published projections:

- applicant task/timeline;
- member application history;
- review queue;
- course workspace counts;
- confirmation/attendance roster;
- facility worklist;
- report/export models.

Projection lag must be visible for staff-critical workflows. Commands never infer success from a stale projection.

## 11. Dependency categories and adapter strategy

### 11.1 In-process dependencies

Examples:

- Workflow -> Course `registrationPolicy`
- Workflow -> Form `validate/freeze`
- Workflow -> People `snapshot`
- Review -> Workflow transition command
- Invitations -> Workflow transition command
- Check-in -> Workflow transition command
- all business modules -> Audit append

Use explicit module interfaces and command/result DTOs. No controller-mediated collaboration.

### 11.2 Local-substitutable dependencies

| Dependency | Production adapter | Test/local adapter | Notes |
|---|---|---|---|
| relational persistence | PostgreSQL repositories | isolated PostgreSQL schema/container | Do not pretend SQLite behavior is PostgreSQL behavior |
| queue/cache/locks | Redis or Valkey, non-cluster for Horizon | local Redis/Valkey | Horizon requires cluster mode disabled |
| document store | S3-compatible object store | local filesystem/in-memory store | Interface includes metadata, stream, signed grant, delete policy |
| clock | system clock | fixed/advancing clock | Required for windows, expiry, retries |
| report artifact store | object store | local temp store | Same retention/hash contract |

### 11.3 Remote but owned dependencies

If the deployment team owns a remote database, Redis, object store, or identity-reader relay, treat network failure explicitly but keep the same local-substitutable interface. Ownership determines operational control; remoteness alone does not justify a business service split.

### 11.4 True external dependencies

| Capability | Current evidence | Preferred target | Comparison and decision |
|---|---|---|---|
| transactional email | SMTP config, `EmailServices`, many Blade mail templates; target direction mentions Postmark | Postmark adapter + fake | Strong transactional delivery, templates/streams, webhooks, suppression visibility. Keep provider behind `MailGateway`; do not couple templates to provider syntax. |
| account email verification | `TwilioVerifyService` using Twilio Verify email OTP | Signed verification link through Postmark; Twilio Verify adapter + fake when OTP is contractually required | Signed links reduce code and provider surface. If OTP remains required, keep challenge, attempts, expiry, resend invalidation, and one-use redemption in Identity & Access. Never make provider response the account state. |
| documents | local filesystem default; AWS placeholders | S3-compatible object storage + local adapter | Durable object versioning, signed access, lifecycle policy, malware scanning seam. Provider can be AWS S3, Azure Blob through another adapter, or compatible storage. |
| local Thai identity card | browser/local agent integration in check-in | Browser `IdentityReader` adapter + manual verification adapter | Hardware/browser dependency cannot be assumed on every workstation. Server validates evidence and eligibility. Manual path is mandatory. |
| spreadsheet | PhpSpreadsheet usage/dependency | `SpreadsheetRenderer` using PhpSpreadsheet; CSV renderer alternate | XLSX fidelity for staff; CSV is simpler and streamable for large data. Same report definition feeds both. |
| map | outbound Google Maps links | Keep as ordinary external link initially | No module adapter until a second map implementation or geocoding requirement exists. Add privacy-safe link generation only. |
| fonts | Google-hosted font references/assets possible | self-host required font files | Removes runtime privacy/availability dependency and makes visual regression deterministic. |
| websocket/push | Pusher placeholders | no target until a validated real-time requirement | Polling/SSE can serve job progress. Do not preserve inactive config as a requirement. |
| Mailgun/SES | config placeholders | no target by default | Not confirmed active. Retain only as alternate `MailGateway` adapter if operational requirements demand it. |
| AdminLTE website/reference | vendored assets and links | remove | It is a legacy visual dependency, not a business integration. |

Provider selection is configuration inside the adapter implementation. Business module interfaces mention intent, recipient, and evidence—not provider message IDs except in internal delivery records.

## 12. Target CSS and experience architecture

### 12.1 Token source

Canonical source and generated outputs:

```text
resources/design/
  tokens/
    reference.tokens.json
    semantic.tokens.json
    typography.tokens.json
    layout.tokens.json
    motion.tokens.json
  build/
    build-tokens.mjs
  generated/
    tokens.web.css
    tokens.tailwind.css
    tokens.ts
    tokens.email.php
    tokens.print.css
```

The DTCG JSON files are the only token source. A deterministic build generates CSS custom properties, Tailwind theme input, TypeScript token names, and email/print adapters. Blade, React, print, and email implementations consume generated semantic values.

Do not edit generated files or duplicate token values in Tailwind configuration, PHP arrays, implementation files, or email templates.

Private primitive token families:

- color scales;
- spacing;
- type size/leading/weight;
- radius;
- shadow;
- motion duration/easing;
- breakpoint/container;
- z-index layers.

Public semantic tokens:

- `surface-page`, `surface-default`, `surface-elevated`, `surface-inverse`;
- `text-default`, `text-muted`, `text-inverse`, `text-link`;
- `border-default`, `border-strong`, `focus-ring`;
- `action-primary`, `action-secondary`, `action-destructive`;
- `status-draft`, `submitted`, `review`, `invited`, `confirmed`, `checked-in`,
  `completed`, `warning`, `error`;
- `sensitive-default`, `sensitive-restricted`;
- `table-sticky-surface`, `table-sticky-border`.

Status meaning must include text/icon/shape, never color alone.

### 12.2 CSS layers

```css
@layer reset, tokens, base, primitives, patterns, templates, utilities, legacy;
```

- `reset`: modern baseline.
- `tokens`: canonical custom properties.
- `base`: typography, document, focus, form defaults.
- `primitives`: smallest reusable controls.
- `patterns`: recurring task compositions.
- `templates`: shell and page-region layout.
- `utilities`: controlled Tailwind utilities.
- `legacy`: frozen interoperability rules, loaded only by legacy routes.

### 12.3 Primitives

- Button
- Link
- Heading/Text
- Icon
- Field/Label/Description/Error
- Input
- Select/Combobox
- Checkbox/Radio
- Textarea
- DateField
- Badge
- Alert
- Dialog
- Drawer
- TableCell
- Spinner/Progress

Primitive interfaces use controlled variants, not arbitrary styling props.

Example controlled variant dimensions:

```text
intent: primary | secondary | quiet | destructive
size: sm | md | lg
density: comfortable | compact
state: default | loading | disabled
```

Use typed variant maps or CVA. Ban page-specific variant names such as `greenCourseButton` or `backendBlue`.

### 12.4 Patterns

- FormStep
- StepProgress
- ApplicationTaskList
- ApplicationTimeline
- CourseCard
- FilterBar
- WorkQueue
- DataGrid
- MobileRecordCard
- StatusBadge
- SensitiveValue
- BulkActionReview
- EmptyState
- ErrorState
- FileLink
- CheckinReadiness
- ConfirmationOutcome

Desktop `DataGrid` and `MobileRecordCard` are rendered from the same field/action schema. Mobile does not hide functionality.

### 12.5 Templates

- PublicShell
- AuthShell
- ApplicationShell
- WorkspaceShell
- PrintShell
- EmailShell

Templates provide landmarks, navigation, content width, task actions, error summary location, and responsive slots. They do not contain course/application rules.

### 12.6 Responsive rules

Validation widths:

- 320 px
- 375 px
- 768 px
- 1,024 px
- 1,440 px

Rules:

- content-first CSS; no server-side user-agent page fork;
- minimum 44 × 44 CSS-pixel touch targets;
- one functional action set at every width;
- grids collapse by schema into record cards or scroll regions with visible affordance;
- sticky columns use semantic surface/border tokens and tested stacking contexts;
- no horizontal page scroll at 320 px except a deliberately contained data region;
- print layout has its own template and snapshot.

### 12.7 Accessibility

Target: WCAG 2.2 AA.

Required:

- semantic landmarks and heading order;
- correct Thai/English `lang`;
- skip link;
- visible keyboard focus;
- full keyboard interaction for dialogs, menus, grids, and bulk action;
- persistent label and description associations;
- error summary linked to invalid fields;
- `aria-live` only for meaningful dynamic step/status changes;
- focus placement after navigation/conditional reveal;
- reduced-motion support;
- high-contrast resilience;
- no status conveyed by color alone;
- 200% zoom and reflow;
- screen-reader-safe sensitive-value reveal;
- accessible PDF/print output where required.

### 12.8 Legacy Bootstrap migration

Asset isolation is mandatory:

- legacy routes load the frozen Bootstrap/AdminLTE/jQuery bundle;
- migrated routes load the Vite/Tailwind/Design System bundle;
- never load both bundles on one route;
- no new Bootstrap or AdminLTE dependency in migrated code;
- no global selector from the new bundle may target legacy markup.

Phases:

0. Record route inventory, screenshots, breakpoints, print/email baselines, and active plugin usage.
1. Freeze legacy CSS. Allow only production-defect fixes in `legacy`.
2. Build tokens, primitives, patterns, templates, and visual fixtures.
3. Migrate route families:
   - public catalog/content and auth;
   - applicant V2 workflow;
   - staff Course Workspace/review;
   - reports/print;
   - check-in.
4. Remove user-agent forks and duplicate mobile views.
5. Remove AdminLTE, Bootstrap, jQuery/plugins, unused static HTML, and old assets only after route telemetry and owner approval show zero use.

Policy:

- no raw hex color outside `resources/design/tokens/*.json`, test fixtures, and documented external-media interoperability;
- no inline `style`;
- no `!important` outside a time-bounded legacy interoperability exception;
- no ID selectors;
- bounded specificity and nesting;
- every token change gets a visual diff;
- semantic token names are versioned/deprecated before removal;
- Design System changes require design-system and frontend code owners.

## 13. CI, test, and visual-regression architecture

### 13.1 Reproducible build

- Replace the incomplete Mix setup with Vite.
- Commit `composer.lock` and npm `package-lock.json`.
- Use `composer install` and `npm ci`.
- Build once into an immutable container/image.
- Generate an SBOM and provenance/signature for the deployable artifact.
- Remove committed third-party assets as route families migrate; install pinned packages through the build where possible.

### 13.2 Pull-request checks

Backend:

- `composer validate --strict`;
- locked dependency installation and vulnerability audit;
- Laravel Pint;
- PHPStan/Larastan at an enforced increasing level;
- PHPUnit module-interface and workflow tests;
- integration tests against the target PostgreSQL version;
- Redis/Valkey non-cluster fixture for queue/cache/lock behavior;
- authorization tests for every staff capability and public token action;
- migration/ETL dry-run and reconciliation checks.

Frontend:

- `npm ci`;
- dependency audit;
- `tsc --noEmit`;
- ESLint;
- Prettier check;
- Stylelint:
  - raw colors restricted to token source;
  - no ID selectors;
  - no `!important` in new code;
  - maximum specificity and nesting;
  - legacy imports forbidden in new bundles;
- Vite production build;
- JavaScript/CSS bundle budgets;
- unused asset/import checks.

Architecture:

- modules may import another module only through its public interface package/namespace;
- direct cross-module ORM/table access fails CI;
- HTTP adapters cannot import repositories directly;
- Notification provider calls are allowed only in worker adapter implementation;
- business modules cannot import controllers, views, or framework requests;
- Design System cannot import business modules;
- Reports may read only published projections.

Browser/accessibility:

- Playwright critical journeys at 320, 375, 768, 1,024, and 1,440 px;
- axe checks on every stable page fixture and journey;
- keyboard-only smoke flows;
- print snapshot and PDF-content checks;
- email rendering fixtures.

Security:

- secret scanning;
- dependency/license policy;
- SAST;
- CSRF/CORS/security-header tests;
- public-token expiry/replay tests;
- PII logging tests.

### 13.3 Visual regression

Stable fixtures:

- every primitive state and controlled variant;
- every pattern state including empty/error/loading/restricted;
- all templates;
- catalog/list/detail;
- every applicant step and error summary;
- application timeline/statuses;
- review queue/detail/bulk confirmation;
- check-in readiness/success/failure;
- report/print;
- representative emails.

Rules:

- fixed time, locale, fonts, animation, and deterministic fixture data;
- compare supported widths and light/high-contrast modes as applicable;
- attach image diffs to the PR;
- baselines are never auto-updated after failure;
- baseline changes require named UX owner approval;
- token changes fan out to all affected snapshots.

Nightly:

- full supported browser matrix;
- complete visual suite;
- broken-link and document checks;
- dependency and container scan;
- representative restore/recovery validation.

### 13.4 Deployment governance

Pipeline:

1. build and sign immutable image;
2. deploy isolated preview environment;
3. run target-schema migration dry-run and reconciliation;
4. smoke health, login, catalog, application task, review queue, invitation action, and check-in;
5. manual production approval;
6. rolling deployment;
7. terminate/restart Horizon workers cleanly;
8. health/readiness and synthetic flow verification;
9. rollback application image; database migrations use expand/contract and forward-compatible rollback.

Repository governance:

- protected main/release branches;
- required checks and review;
- `CODEOWNERS` by module and Design System;
- PR template fields for module interface, state ownership, token impact, migration seam, security/PII, and rollback;
- no direct server Git pull as the final target deployment mechanism.

## 14. Migration seams and sequence

### 14.1 Preconditions

1. Capture live DDL, indexes, constraints, row counts, null/range distributions, and status values.
2. Compare live schema with 6 Laravel migrations, 19 `db_scripts` SQL files, and 2 root reference SQL datasets.
3. Inventory production routes, legacy token traffic, files, emails, exports, scheduled/manual operational tasks, and hidden check-in workstations.
4. Define canonical IDs, state vocabulary, retention, and PII classification.
5. Establish golden source reports and representative applicant/course cohorts.

### 14.2 Required seams

| Seam | Purpose | Removal evidence |
|---|---|---|
| legacy route adapter | preserve bookmarks/action links | zero observed traffic through defined retention window |
| Laravel token decoder/exchange | accept old invitation/application tokens once | all live tokens expired/exchanged |
| legacy password verifier/rehash | preserve account access | all active accounts rehashed or reset |
| legacy table read adapter | phased read/cutover | reconciliation complete and new owner is sole reader |
| ETL mapping/quarantine | transform ambiguous rows safely | zero unresolved critical rows; signed reconciliation |
| report oracle | compare new reports to trusted old outputs | owner-approved golden parity or documented intentional difference |
| document URL redirect | preserve links in emails/bookmarks | retention/telemetry threshold met |
| legacy CSS route bundle | keep non-migrated pages stable | last legacy route removed |
| notification dual-observation | compare provider/template behavior without duplicate sends | delivery acceptance criteria met |
| card-reader adapter | isolate workstation hardware | browser/manual adapter contract tests pass |

### 14.3 Coexistence ownership

For every migration phase, publish a matrix:

| Aggregate | Current reader | Current writer | Target reader | Target writer | Cutover signal |
|---|---|---|---|---|---|
| account | legacy | legacy | target | one only | auth cohort enabled |
| person profile | legacy | one only | target | one only | ETL + reconciliation |
| application | legacy | one only | target | one only | course cohort selected |
| review | legacy | one only | target | one only | all target submissions |
| invitation | legacy | one only | target | one only | token adapter active |
| documents | legacy URL | legacy/target by class | target | target | redirect inventory complete |

Never bidirectionally dual-write an aggregate. Use:

- read-through adapter;
- one-time or incremental ETL;
- outbox-fed projection;
- explicit ownership cutover.

Course/session cohort cutover is safe only after shared account, person, reference, and document ownership is defined. Do not split one applicant's application lifecycle between implementations.

### 14.4 Recommended delivery order

1. Platform baseline: CI, target runtime, observability, live-schema capture, security closure.
2. Design System foundation and public/auth vertical slice.
3. Identity, People, Reference, and Course interfaces/adapters.
4. Form Engine plus Application Workflow draft/submission vertical slice.
5. Review and course workspace.
6. Invitations, durable Notifications, and secure action results.
7. Check-in and Operations.
8. Reports/exports/print and golden parity.
9. Legacy route/data/token retirement and asset removal.

Each slice includes production interface, fake/provider adapter where justified, state migration, UX, accessibility, visual fixtures, security, telemetry, rollback, and retirement criteria.

## 15. Workflow and UX improvements enabled by the module design

### 15.1 Applicant

Current fragmentation becomes one task-oriented journey:

```text
Find course
  -> Eligibility and registration decision
  -> Start/resume application
  -> Complete visible task list
  -> Review submission snapshot
  -> Submit
  -> Track status and required actions
  -> Respond to invitation
  -> Prepare for / complete check-in
```

Improvements:

- persistent save/resume with optimistic concurrency;
- progress based on server validation, not page visitation;
- one error summary and field-level guidance;
- conditional teacher/manager tasks from versioned Form Engine rules;
- review-before-submit snapshot;
- timeline with plain-language status, reason, next action, and deadline;
- account/profile changes separated from historical submission;
- responsive one-page implementation rather than mobile/desktop forks.

### 15.2 Staff

Current route/controller silos become a course-centered workspace:

- Overview: counts and exceptions from published projections.
- Applications: search/filter and applicant detail.
- Review: assignment, criteria, notes, decision.
- Invitations: issuance, delivery, response, retry.
- Attendance: readiness, identity verification, check-in.
- Operations: laundry/facility worklists.
- Reports: versioned, asynchronous exports.
- Audit: sensitive action timeline.

Bulk operations require:

1. explicit selection;
2. preview of affected/blocked records;
3. reason where material;
4. confirmation;
5. idempotent job;
6. result report with per-record status.

### 15.3 Check-in

- course/operator context always visible;
- search/manual/card-reader paths share one readiness result;
- readiness explains blockers: not confirmed, wrong session, already checked in, identity mismatch;
- manual fallback with stronger audit;
- success screen contains the next operational action;
- hardware failure never blocks all check-in.

## 16. Main risks and controls

| Risk | Evidence | Control |
|---|---|---|
| repository schema is incomplete | 6 migrations plus 19 manual scripts and staging-only table references | live DDL/data profiling before schema design or estimates |
| status ambiguity | labels/messages/config and mutations spread across controllers/helpers | canonical state workshop, mapping table, state-machine tests |
| numeric question-ID coupling | SQL scripts and question services use fixed IDs/AUTO_INCREMENT assumptions | semantic keys + versioned Form Engine + migration map |
| PII and public-action exposure | broad CSRF exemption, public tokens, large profile surface | CSRF allowlist, hashed one-time tokens, capability policy, PII audit/log tests |
| cross-cohort shared-state conflict | account/profile/course/application are coupled | one-writer coexistence matrix; no bidirectional dual-write |
| report drift | large controller-built reports and helper formatting | golden oracle, published projections, versioned report definitions |
| delivery state corrupts business state | synchronous mail and weak command behavior | transaction/outbox; Notifications owns delivery only |
| disabled scheduling/queue | sync queue and commented scheduler | worker/scheduler health, durable retries, dead-letter operations |
| card-reader availability | local hardware/browser integration | adapter, manual fallback, workstation certification |
| Bootstrap/Tailwind collision | huge global legacy CSS and inline rules | route-level bundle isolation; never mix bundles |
| non-reproducible assets | missing Mix sources, no JS lockfile, 133 MB public tree | Vite, lockfile, immutable build, asset inventory/budgets |
| no CI/manual deploy | no workflow, SSH/Git deployment | required pipeline, protected branches, signed artifact |
| orphan code/hidden requirement | `NewFlow` missing views; test/dev routes | route telemetry and named product validation before deletion |
| user-agent rendering forks | separate mobile signup and `is_mobile` branches | one responsive DOM/task schema |
| print/email parity | separate templates and inline styles | versioned renderers, print/email visual fixtures |
| Thai date/text/collation | helper-based formatting and Thai content | explicit locale/calendar utilities, PostgreSQL collation tests, golden reports |
| old URLs/tokens/documents | mail/bookmark action links | exchange/redirect adapters with telemetry and retention |
| weak automated confidence | source-shape-heavy tests | module interface, DB integration, journey, accessibility, and visual tests |
| vendored supply-chain surface | AdminLTE/plugins committed under `public` | inventory, vulnerability scan, pinned build dependencies, retirement |

## 17. Architecture acceptance checklist

The replacement PRD is implementation-ready only when:

- every route/page has the single owner listed here or an approved correction;
- every live table/column has a source-to-target mapping and reconciliation rule;
- each module has one small public interface and at least one production implementation;
- every true external seam has a fake implementation and provider-contract test;
- no business status can be set through generic CRUD;
- immutable submission/profile/answer/consent snapshots are specified;
- Review decisions reference exact submissions;
- Notifications are outbox-driven and cannot mutate workflow state;
- reports use published projections and golden parity;
- legacy routes/tokens/files have telemetry and removal criteria;
- Design System tokens are the only new visual source of truth;
- migrated routes do not load Bootstrap/AdminLTE/jQuery;
- all key tasks pass WCAG 2.2 AA checks at the five required widths;
- CI enforces architecture, backend, frontend, security, accessibility, visual, and migration checks;
- deployment is an immutable artifact with health, smoke, rollback, worker, and migration controls;
- PII classification, retention, audit, and authorization are explicit;
- open requirements for `Parole`, orphan NewFlow, placeholder routes, and hidden operational commands are resolved by a named owner.

## 18. Explicit non-goals and cautions

- Do not copy the current controller boundaries into the new framework.
- Do not treat every current route, status label, SQL script, or helper as a requirement.
- Do not discard a current behavior until usage/owner evidence classifies it as obsolete.
- Do not create microservices to simulate module depth.
- Do not create generic repository/utility interfaces with no alternate implementation or invariant.
- Do not use the report database as a backdoor around module ownership.
- Do not add a new design system while continuing page-level raw color, inline style, or arbitrary variants.
- Do not claim schema completeness from the repository alone.

The architectural target is a set of deep modules with narrow interfaces, strong locality, deliberate seams, swappable adapters where justified, and enough leverage that business-policy or design-token changes occur once.
