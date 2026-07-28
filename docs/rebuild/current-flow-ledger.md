# Tapoda Current Flow Ledger

**Status:** Static-code baseline for parity and redesign

**Repository baseline:** `uat-20260526` at `3d2c3a4`

**Evidence scope:** 109 concrete source route declarations, 91 Blade files, 8 root HTML artifacts, 12 public PDFs, commands, mail templates, and direct database effects. Runtime availability is conditional: `W099` is local-only, `W010` is local-gated, and `W057` has no callable action.

This ledger records observable current flows before redesign. The row-level route source is [`current-page-inventory.csv`](current-page-inventory.csv). A flow is not safe to remove when it appears defective, duplicated, dormant, or insecure. It needs an approved replacement or retirement decision.

## 1. Logging rules

Each flow has:

- Stable flow ID.
- Actor and entry.
- Current pages/actions and branch conditions.
- Reads, writes, lifecycle effects, and external effects.
- Known success, validation, empty, denied, expired, and technical-failure behavior.
- Evidence status.
- Target owning module.
- A behavior-preserving parity proof.

Flow changes require updates to this file, the endpoint ledger, [`coverage-matrix.md`](coverage-matrix.md), and the PRD acceptance criteria.

## 2. Flow catalog

| Flow ID | Current capability | Actor | Endpoint IDs | Evidence | Target owner and consulted modules |
|---|---|---|---|---|---|
| `FLOW-PUB-01` | Public discovery and course detail | Visitor, applicant | `W002-W003`, `W006-W008`, `W019-W022` | `verified-code` | Course Catalog & Sessions |
| `FLOW-AUTH-01` | Registration, OTP, and consent | Visitor | `W011`, `W014`, `A003-A007` | `verified-code` | Identity & Access; Documents & Consent |
| `FLOW-AUTH-02` | Applicant sign-in, current session, and sign-out | Applicant | `W012-W013`, `A002` | `verified-code` | Identity & Access |
| `FLOW-AUTH-03` | Password recovery | Applicant | `W015`, `W018` | `verified-code` | Identity & Access; Notifications |
| `FLOW-REF-01` | Thai address dependent selection | Visitor, applicant | `W019-W020` | `verified-code` | Reference Data |
| `FLOW-APP-01` | Start or resume an application | Applicant, alumni | `W021-W022`, `W027-W028`, `W045` | `verified-code` | Application Workflow; Legacy Compatibility |
| `FLOW-APP-02` | Guided initial application, new person | Applicant | `W027-W029`, `W032-W033`, `W040-W041` | `verified-code` | Application Workflow; Form Engine |
| `FLOW-APP-03` | Guided initial application, alumni | Alumni | `W027-W033`, `W040-W041` | `verified-code` | Application Workflow; Form Engine |
| `FLOW-APP-04` | Guided post-invitation confirmation, new person | Invited applicant or staff applicant | `W027-W029`, `W032-W039` | `verified-code` | Application Workflow; Invitations & Confirmations; Form Engine |
| `FLOW-APP-05` | Guided post-invitation confirmation, alumni | Invited alumni or staff applicant | `W027-W039` | `verified-code` | Application Workflow; Invitations & Confirmations; Form Engine |
| `FLOW-APP-06` | Legacy initial application | Applicant | `W045-W051` | `verified-code` | Legacy Compatibility delegating to Application Workflow |
| `FLOW-INV-01` | Legacy invitation acceptance | Invited applicant or staff applicant | `W052-W062` | `verified-code` with route gap | Legacy Compatibility delegating to Invitations & Confirmations |
| `FLOW-INV-02` | Email-link confirm, cancel, and message | Applicant | `W016-W017`, `W023-W026` | `verified-code` with partial-failure defect | Invitations & Confirmations |
| `FLOW-MEMBER-01` | Member profile, history, and security | Applicant, alumni | `W042-W044` | `verified-code` | People & Profiles; Application Workflow; Identity & Access |
| `FLOW-ADMIN-01` | Administrator authentication and landing | Administrator | `W063`, `W091-W092`, `W098` | `verified-code` | Identity & Access; Design System |
| `FLOW-REVIEW-01` | Review queue, applicant inspection, and decision | Reviewer, course manager | `W064-W067` | `verified-code` | Review & Selection |
| `FLOW-COURSE-01` | Course/session operations and participant status | Course manager | `W068-W070`, `W073`, `W075`, `A009` | `verified-code`; one placeholder endpoint | Course Catalog & Sessions; Application Workflow |
| `FLOW-INV-03` | Request confirmation and reminder preview | Course manager, system worker | `W074`, `W099` | `verified-code`; schedule disabled | Invitations & Confirmations; Notifications |
| `FLOW-MAINT-01` | Console invitation/confirmation maintenance | System worker, manual operator | No HTTP endpoint; `invite:accept`, `request:confirm` | `verified-code`; schedule disabled | Legacy Compatibility; Invitations & Confirmations |
| `FLOW-OPS-01` | Laundry and facilities worksheet | Operations staff | `W071-W072` | `verified-code` | Operations & Facilities; Reports & Exports |
| `FLOW-CHECKIN-01` | Course-scoped operator access and check-in | Check-in operator | `W084`, `W093-W097`, `A008` | `verified-code`; public PII defect | Check-in & Attendance; Identity & Access |
| `FLOW-REPORT-01` | Applicant report and eight-sheet export | Reporting user | `W086-W087` | `verified-code`; rule divergence | Reports & Exports |
| `FLOW-REPORT-02` | Teacher list, detail, selection, and print | Teacher reviewer | `W088-W090` | `verified-code` | Reports & Exports |
| `FLOW-ADMIN-02` | Backend account administration | System administrator | `W076-W081`, `A010` | `verified-code`; unsafe mutation/enumeration | Identity & Access |
| `FLOW-PAROLE-01` | Parole pages | Administrator | `W082-W083` | `verified-code`; purpose miswired/unknown | Unknown pending owner decision |
| `FLOW-NOTIFY-01` | Mail rendering, preview, and delivery variants | System worker, developer | `W009-W010`, plus effects in other flows | `verified-code` | Notifications |
| `FLOW-MSG-01` | Generic result and success messages | Applicant | `W016-W017` | `verified-code` | Design System; Legacy Compatibility |
| `FLOW-DEBUG-01` | Diagnostic and placeholder endpoints | Public, developer, administrator | `W001`, `W004-W005`, `W063`, `W085`, `A001`, `A009` | `verified-code`; unsafe/placeholder | Platform Operations; owner decision |
| `FLOW-ART-01` | Static prototypes, root PHP artifacts, and public PDFs | Unknown | No Laravel endpoint IDs | `verified-artifact` | Documents & Consent; Legacy Compatibility |

## 3. Public, identity, and member flows

### `FLOW-PUB-01` — Public discovery and course detail

| Field | Current flow |
|---|---|
| Entry | `/`, `/course`, `/course/detail/{course_code}`, `/suggest`, `/applicant-qualifications`, `/about` |
| Main steps | Open catalog → filter year/month/type/center → inspect course → authenticate or start application |
| Branches | Registration state; gender/category eligibility; age; course type; trainee/staff application; authenticated application state |
| Reads | `course`, `center`, `course_type`, `tutor_type`, `apply_course`, `contact`, reference masters |
| Writes | None on ordinary discovery |
| External effects | Course attachments and outbound map links |
| Current outcomes | Course cards/details; eligibility or unavailable messaging; empty list behavior is view-specific |
| Gaps | Center control behavior requires runtime confirmation. Filters are not consistently shareable. Layout has fixed widths, duplicated frameworks, wrong `lang`, and zoom restriction. |
| Target | Public catalog page template. Server-owned filter query. Course policy interface. Accessible empty/error/loading states. |
| Parity proof | Golden scenarios for each filter and eligibility branch; attachment/link inventory; responsive and WCAG snapshots. |

### `FLOW-AUTH-01` — Registration, OTP, and consent

| Field | Current flow |
|---|---|
| Entry | `GET /signup`; `POST /api/signup/otp/request`; `POST /api/signup/otp/verify`; `POST /api/signup` |
| Main steps | Render desktop/mobile form by User-Agent → request Twilio email OTP → verify OTP → cache registration token → submit identity/profile → create account/contact → sign in → send welcome email |
| Branches | Personal ID available/exists; OTP accepted/rejected/expired; desktop/mobile view; agreement link |
| Reads | `users`, `contact`, reference tables, OTP provider and cache |
| Writes | `users`, `contact`, session, cached verification token |
| External effects | Twilio Verify request/verification; welcome email |
| Current outcomes | Successful registration authenticates user. Availability endpoints reveal identifier existence. Validation and provider failure response shapes differ. |
| Gaps | Device-specific duplicate templates; enumeration; provider state split from account transaction; consent version not first-class. |
| Target | One responsive registration template. `RegistrationChallenge` with hashed/one-use proof. Versioned consent receipt. Provider adapter and fake implementation. |
| Parity proof | OTP request/verify/replay/expiry/rate-limit tests; duplicate identity tests; single responsive UI matrix; welcome delivery audit. |

### `FLOW-AUTH-02` — Applicant sign-in and sign-out

| Field | Current flow |
|---|---|
| Entry | `POST /signin`; `GET /signout` |
| Main steps | Submit personal ID/password → `Auth::attempt` → session → redirect/JSON; signout clears session |
| Branches | Valid/invalid credentials; applicant/admin redirect behavior elsewhere |
| Writes | Session state |
| Gaps | Signout mutates over GET. Error/accessibility contract is not centralized. |
| Target | POST logout, rotation and throttling, uniform non-enumerating error, intended-destination restoration. |
| Parity proof | Valid/invalid/locked/throttled/session-fixation/logout tests. |

### `FLOW-AUTH-03` — Password recovery

| Field | Current flow |
|---|---|
| Entry | `GET /forgot`; `POST /forgot-password` |
| Main steps | Enter personal identifier → locate user → generate a new plaintext password → overwrite hash → email plaintext replacement |
| Writes | `users.password` |
| External effects | Synchronous `mail.reset` |
| Gaps | Critical: identifier-only reset, account enumeration risk, plaintext secret in email, destructive credential overwrite, mail failure after mutation. |
| Target | Expiring one-use recovery challenge, neutral response, no plaintext secret, atomic redemption, session revocation, audit. |
| Parity proof | Known/unknown identity indistinguishable; expiry/replay; successful reset; delivery failure does not corrupt current credential. |

### `FLOW-REF-01` — Thai address dependent selection

| Field | Current flow |
|---|---|
| Entry | `/select/amphoes?province_id=…`; `/select/tambons?amphoe_id=…` |
| Main steps | Province → amphoe → tambon/postcode |
| Reads | `amphoes`, `tambons` |
| Gaps | Missing query inputs can leave response variables undefined. Contract is embedded in route closures. |
| Target | Reference Data query interface with typed request/response, stable empty result, cache and localization. |
| Parity proof | Valid, unknown, missing, and malformed parent identifier contract tests. |

### `FLOW-MEMBER-01` — Member profile, application history, and security

| Field | Current flow |
|---|---|
| Entry | `/member/info/{action?}` with profile/applications/history/password tabs |
| Main steps | View account/profile → edit contact/profile → inspect applications and training history → change password |
| Reads | `users`, `contact`, profile references, `apply_course`, course/session, training history, teacher data |
| Writes | `users`, `contact`, password |
| Gaps | One 886-line page owns unrelated tab behavior. Change-password response echoes request data. Current and historical concepts are not separated. |
| Target | Member shell composing Profile, Application Timeline, Training History, and Security settings from owning modules. |
| Parity proof | Tab deep links, own-record authorization, edit validation, history order, password redaction, empty-state snapshots. |

## 4. Application and confirmation flows

### `FLOW-APP-01` — Start or resume

| Field | Current flow |
|---|---|
| Entry | Course detail application link; `/course/apply/{apply_token}`; V2 profile; legacy training detail |
| Main steps | Decode temporary token → resolve user/course/type → find application or create draft → choose V2/legacy and current valid step |
| Branches | Authenticated or redirect; new/alumni; trainee/staff; pre/post invitation; current stored step |
| Writes | A valid V2 GET can insert `apply_course` draft with step `user_info` |
| Gaps | Read request has creation side effect. Flow selection and application creation are coupled. Token compatibility is implicit. |
| Target | Explicit POST `startApplication`; GET `resumeApplication`; Compatibility adapter exchanges active legacy tokens. |
| Parity proof | Token matrix, duplicate-start idempotency, authorization, each variant/step redirect, expired/tampered token. |

### Guided variant matrix

| Flow ID | Variant | Current ordered stages | Submission/terminal effect |
|---|---|---|---|
| `FLOW-APP-02` | `pre-new` | Profile → Preferences → PDPA | `draft|applying|applicant_pending → applied`; set `applied_date` |
| `FLOW-APP-03` | `pre-alumni` | Profile → Training History → Preferences → PDPA | Same transition |
| `FLOW-APP-04` | `post-new` | Profile → Preferences → Teacher Details → Commitment → Management Details | Invited flow can become `confirmed`; set `confirmed_date`; send confirmation |
| `FLOW-APP-05` | `post-alumni` | Profile → Training History → Preferences → Teacher Details → Commitment → Management Details | Same transition |

Shared current behavior:

1. `GuidedFlowService::resolveOrRedirect` chooses the variant and legal step.
2. Profile writes shared `users`/`contact` plus application and manager detail.
3. Training History writes row history, attendance periods, and coarse step; draft may become `applying`.
4. Preferences writes question groups 11/12.
5. Teacher Details writes group 14 with hard-coded question IDs.
6. Commitment writes group 9, emergency/representative data, and acknowledgements.
7. Management Details writes group 13, travel and facility data.
8. PDPA records only a checked input and application state/date.

Shared gaps:

- Forms, domain persistence, flow navigation, and email effects live in controllers and large Blade pages.
- Question identity and conditional logic are hard-coded. Submitted group IDs and some state fields are client-influenced.
- Autosave, draft ownership, cross-field validation, error focus, and resumption are inconsistent.
- Shared profile mutation can alter information viewed by historical applications.
- No immutable application submission binds the exact answers, profile, consent, and rules reviewed.
- Unknown gender can fall through unsafe question assumptions.

Target:

- Form Engine owns immutable form definitions, sections, fields, conditions, validation, and answer schemas.
- Application Workflow owns draft, ordered stage eligibility, immutable submission, and state transition.
- People & Profiles owns the reusable current profile. Submission snapshots preserve history.
- Documents & Consent owns consent text/version/acceptance.
- Notifications receives outbox events after committed transitions.

Parity proof:

- One scenario for every variant, applicant classification, travel choice, attendance mode, question condition, validation failure, back/forward navigation, refresh, and resume.
- State and database snapshot after each stage.
- Submission immutability and historical-read tests.
- Accessible render snapshots for every question section, including hidden/revealed conditions and server errors.

### `FLOW-APP-06` — Legacy initial application

| Field | Current flow |
|---|---|
| Entry | Training detail → question → agreement |
| Main steps | Save profile/application → save training history → save question answers → accept agreement |
| Reads/writes | Same shared profile, application, manager, history, and answer tables as V2 |
| State | Draft can become `applying`; final agreement sets `applied` |
| Gaps | Display authorization and save authorization differ. Request supplies application IDs, step, and status. `apply/user-detail` is behind an unrouted action. |
| Target | Thin Legacy Compatibility adapter maps legacy payloads to the same Application Workflow and Form Engine commands. No direct legacy state setter. |
| Parity proof | Captured legacy payload fixtures; ownership attacks; exact redirect/result compatibility; audit of each translated command. |

### `FLOW-INV-01` — Legacy invitation acceptance

| Field | Current flow |
|---|---|
| Entry | User detail → training detail → question → more info → consent |
| Writes | Shared profile/application/manager/history/answers plus `invite_accept` |
| Branches | Staff/trainee, new/alumni, question groups, accept/decline |
| State | Decline writes legacy `rejected`; accept writes `confirmed` and sends staff/trainee confirmation |
| Gaps | `/accept/done` targets missing `AcceptController::done`. Stale AJAX calls nonexistent `/accept/save-apply-question`. Applicant decline and reviewer rejection share `rejected`. |
| Target | Legacy payload adapter to invitation response and guided post-invitation forms. Distinct `declined_by_applicant`. |
| Parity proof | Each five-step branch, accept/decline, stale-link handling, double-submit idempotency, mail/outbox result, missing-done replacement. |

### `FLOW-INV-02` — Email-link confirmation and cancellation

| Field | Current flow |
|---|---|
| Entry | Encrypted `apply_token` links from email; generic result pages |
| Main steps | Decode token → find application → confirm or cancel/leave → notify/redirect |
| State | `approved → confirmed` in one path; cancel aliases can write cancellation/leave state |
| Gaps | State-changing GET. Link lifetime and one-use behavior are not explicit. Three cancellation aliases produce different response shapes. `CourseController@confirm` writes state then calls undeclared `$this->userServices`, allowing transition-before-fatal. |
| Target | Hashed, expiring, one-use Action Token. GET preview followed by POST decision. Transaction + outbox. Active legacy-link exchange adapter. |
| Parity proof | Active legacy link inventory; expired/tampered/replayed tokens; preview has no mutation; all aliases map to one outcome; injected delivery failure rolls back or records retry safely. |

## 5. Administration, review, and operations flows

### `FLOW-ADMIN-01` — Administrator authentication

| Field | Current flow |
|---|---|
| Entry | `/backend/login`; `POST /backend/signin`; `/backend/`; `/backend/logout` |
| Main steps | Render login → attempt backend user credential → redirect → placeholder landing; GET logout |
| Gaps | Landing is blank. Login closure and authorization scopes are shallow. Logout mutates over GET. |
| Target | Identity & Access owns authentication and scoped RBAC. Course Workspace is a composed landing experience, not a state-owning module. |
| Parity proof | Role/scope matrix, redirect restoration, denied routes, session rotation, POST logout, audit. |

### `FLOW-REVIEW-01` — Review and selection

| Field | Current flow |
|---|---|
| Entry | Approval course list → segmented application queue → full applicant detail → save decision |
| Branches | New/alumni; male/female/monastic; trainee/staff; willingness to help; course/status |
| Reads | Course/session, application, user/contact, manager, history, questions/answers, related courses, teachers, attendance periods |
| Writes | `apply_course.status`; `invite_accept`; invitation metadata; optional staff position |
| Effects | Invite email. Requested `approved` is stored as `invited`; reject/cancel/leave variants are possible. |
| Current limitation | No first-class review round, reviewer assignment, immutable reviewed submission, criteria, score, note, decision revision, or full event history. Course application rows retain course participation, but only the latest mutable outcome is explainable. |
| Target | Per-course review round bound to exact `application_submission_id`. Mutable private draft; immutable submitted review; separate decision and decision events; correction supersedes; reconsideration opens a new round. |
| Parity proof | Queue membership fixtures; field-visibility RBAC; course A/B history independence; concurrent review; immutable submission; decision reasons; invitation outbox; reviewer/action audit. |

### `FLOW-COURSE-01` — Course/session operations

| Field | Current flow |
|---|---|
| Entry | Course list → course manage |
| Actions | Toggle registration by male/female flags; bulk write applicant status; finalize and mark alumni; configure check-in password; view counters |
| Writes | `course` apply flags/password; `apply_course.status`; `contact.is_alumni` and `is_ask_alumni` |
| Gaps | Bulk endpoint accepts arbitrary status and IDs. No preview, transition policy, reason, idempotency, or partial-failure contract. `/api/apply-stat` returns hard-coded zeros. |
| Target | Course policy interface plus authorized Application Workflow bulk command. Preview, expected version, per-item result, audit, and idempotency key. |
| Parity proof | Registration/capacity scenarios; every allowed/denied transition; mixed bulk result; finalization and alumni reconciliation; accurate projection counters. |

### `FLOW-INV-03` — Confirmation request and reminder

| Field | Current flow |
|---|---|
| Entry | Course manage request-confirm action; local reminder preview |
| Main steps | Select applications → load applicant/course → synchronously send request-confirm email |
| State | Controller does not record a sent flag |
| Schedule | Repository command classes exist, but scheduler entries are disabled |
| Gaps | Delivery and lifecycle are not observable. Resend cooldown/idempotency absent. Preview is local-only. |
| Target | Notification outbox, reminder policy, versioned preview, provider message ID, retry/bounce state, scheduled worker. |
| Parity proof | Due/not-due fixtures; duplicate worker run; resend cooldown; provider failure/retry; delivery audit. |

### `FLOW-MAINT-01` — Console invitation/confirmation maintenance

Both command classes are registered, but every scheduler entry in `app/Console/Kernel.php` is commented.

| Command | Current behavior | Weakness | Required disposition |
|---|---|---|---|
| `request:confirm` | Finds the first course starting ten days ahead, then the first approved trainee with `is_request_confirm = 0`; synchronously sends `mail.request-confirm`; sets the flag after the send call | Processes at most one application, duplicates `W074`, has no idempotency/delivery record, and has no active schedule | Replace with Invitations & Confirmations reminder policy plus Notifications outbox worker; reconcile manual/web behavior |
| `invite:accept {apply_id}` | Ignores the required argument; intended database insert is commented; appends the current timestamp to working-directory `newfile.txt`; reports success | Unscoped file write and false success; no invitation behavior remains | Remove after production cron/manual-script check, or replace through an approved command adapter if a real operation is discovered |

Parity proof: production cron/process inventory, command dry-run fixture, duplicate-run behavior, no untracked file write, and approved retirement/replacement record.

### `FLOW-OPS-01` — Laundry and facilities

| Field | Current flow |
|---|---|
| Entry | Course/gender laundry screen and Excel export |
| Main steps | Filter participant group → inspect room/day 01–08/cost/facility worksheet → download |
| Gaps | UI includes `invited`; export does not. Operational columns are blank worksheet fields rather than persisted facts. Gender segmentation is binary. |
| Target | Operations & Facilities records assigned room, daily laundry, purchases, needs, and adjustments; Reports consumes the same projection. |
| Parity proof | Screen/export row equality; state membership decision; totals and Thai text; course scope; audit; print/XLSX fixture. |

### `FLOW-CHECKIN-01` — Check-in

| Field | Current flow |
|---|---|
| Entry | Admin check-in course list or external course-password login → course form |
| Main steps | Authenticate course operator → search confirmed participant by ID → inspect identity/operational data → save arrival |
| Reads | Course/password, application, user/contact, prefix/type, answers, latest check-in |
| Writes | `checkins`; `apply_course.status = checkin`; course-scoped session |
| Branches | Current hash or legacy plaintext course password; found/not found; already checked in; optional card/manual data |
| Gaps | `A008` PII search has no `checkin.access`. Duplicate-scan idempotency and mismatch policy are incomplete. Status replaces history. |
| Target | Scoped operator grant, minimum-data participant query, optional local card adapter, explicit verify/warn/block policy, append-only attendance event. |
| Parity proof | Course-scope attack, no-result, confirmed-only, duplicate scan, card unavailable/mismatch, manual path, concurrent save, event history. |

### `FLOW-ADMIN-02` — Backend account administration

| Field | Current flow |
|---|---|
| Entry | Account list → add/edit/delete |
| Writes | Backend user create/update/soft delete and password |
| Gaps | Delete mutates over GET. Username availability enumerates publicly. Role/scope, last-admin, self-disable, and reset policy are not systematic. |
| Target | Scoped administration commands, POST/DELETE mutation, invitation/reset workflow, last-admin/self-disable guards, complete audit. |
| Parity proof | Create/edit/deactivate/reset matrix; duplicate username; permission scope; last-admin/self-disable; CSRF and audit. |

### `FLOW-PAROLE-01` — Parole pages

| Field | Current flow |
|---|---|
| Entry | `/backend/parole`; `/backend/parole/detail/{id}` |
| Observed behavior | Controller and views appear to list/detail backend administrator records rather than a proven parole domain. Mutation methods exist but are unrouted. |
| Evidence | `verified-code` for page behavior; `unknown-production` for intended business purpose |
| Decision gate | Product owner and production telemetry must choose restore, rename, migrate, or retire. Do not invent a Parole module. |
| Parity proof | Required only after purpose, owner, data, access, and retention are confirmed. |

## 6. Reporting and communication flows

### `FLOW-REPORT-01` — Applicant report and eight-sheet export

| Field | Current flow |
|---|---|
| Entry | `/backend/report`; `/backend/export` |
| Main steps | Choose course/filter → view eight applicant groupings → inspect sticky-table data → export independently assembled eight worksheets |
| Reads | Application/profile/contact, course, check-in, answers/choices, history, periods |
| Gaps | Screen and export reimplement classification and have different status membership. Very wide tables rely on fixed widths and page-local sticky CSS. Sensitive fields lack field-level policy and export audit. |
| Target | One report projection and classification policy consumed by accessible data grid, print, and queued XLSX adapters. |
| Parity proof | Group-by-group golden dataset; screen/export row and field reconciliation; ordering; sticky-column visual snapshots; authorization and download audit. |

### `FLOW-REPORT-02` — Teacher review and print

| Field | Current flow |
|---|---|
| Entry | Teacher report → JSON-rendered detail modal → select up to ten → POST print |
| Branches | Five participant groups; new/alumni; details and prior history |
| Reads | Course/application/profile/contact, training, teachers, check-in, questions, periods |
| Gaps | Selection rule and sensitive field need owner approval. Modal, table, and print assemble related data separately. |
| Target | Teacher-scoped projection, documented maximum selection rule, field policy, accessible dialog, print adapter. |
| Parity proof | Five group counters, modal fields, exactly 0/1/10/11 selections, ordering, print pagination, field authorization. |

### `FLOW-NOTIFY-01` — Notification rendering and delivery

Current active mail variants:

- Welcome.
- Password reset replacement.
- Invitation and staff invitation.
- D03, D10, and staff legacy confirmation.
- D03, D10, monastic D10, and staff current confirmation.
- Request confirmation.
- Cancellation notice.

Current flow: business controller chooses a Blade template, links/attachments, and invokes synchronous delivery. Failures are frequently swallowed after state writes. Local preview exists for reminder; `/mail` and `/xxxxx/{uid}` expose unsafe development behavior.

Target: versioned notification intent → transactional outbox → audience/template policy → provider adapter → delivery attempt/event. Email design comes from the Design System email adapter. Passwords never appear in templates.

Parity proof: recipient/audience/course matrix, attachments and active links, template snapshots, provider success/failure/retry/bounce, no false “notified” state.

### `FLOW-MSG-01` — Generic result messages

Current generic token-decoded message and static accept-success pages present outcome text outside the main workflow. The target Design System owns accessible Success, Warning, Error, Expired Link, and Next Action patterns; domain modules supply safe message codes and recovery actions.

## 7. Diagnostics and artifact-only flows

### `FLOW-DEBUG-01` — Diagnostic and placeholder endpoints

| Artifact | Current behavior | Required disposition |
|---|---|---|
| `/welcome` | Stock Laravel page | Remove after smoke-route replacement |
| `/test` | JSON callback/debug response with commented experiments | Remove or replace with authenticated diagnostics |
| `/xxxxx/{uid}` | Public mail-trigger route; ignores parameter and contains hard-coded PII | Disable immediately; retain evidence in audit |
| `/mail` | Mail rendering/debug | Local/test-only preview |
| `/backend/` | Blank administrator page | Replace with Course Workspace projection |
| `/backend/summary` | Placeholder summary | Implement approved projection or retire |
| `/api/test` | Returns literal `true` | Replace with safe liveness/readiness endpoints |
| `/api/apply-stat/{course_code}` | Hard-coded zero counters | Replace with projection or retire |

### `FLOW-ART-01` — Artifact-only pages and documents

- Eight root static HTML files are design/prototype artifacts, not proven Laravel routes.
- Two root PHP files are deployment/legacy artifacts and need hosting evidence.
- Twelve public PDFs are current document inventory, not proof of active use.
- Six Blade files are orphan/unknown, including `apply/user-detail` behind an unrouted action.
- `NewFlow/ApplyWizardController` is dormant and references six nonexistent `apply-new/*` views.

Each artifact receives one disposition: `migrate`, `adapter`, `archive`, or `remove`. `archive` and `remove` require telemetry, owner, retention, link, and rollback evidence.

## 8. Current lifecycle effect ledger

These are observed labels, not the approved target state machine:

| Current transition/effect | Triggering flow | Weakness to remove |
|---|---|---|
| Create `draft` on GET | `FLOW-APP-01` | Read-side mutation |
| `draft → applying` | Training-history saves | Step completion and lifecycle mixed |
| `draft|applying|applicant_pending → applied` | V2 PDPA or legacy agreement | Consent/submission snapshots absent |
| Requested `approved` stored as `invited` | `FLOW-REVIEW-01` | Action/state naming mismatch |
| `approved → confirmed` | Direct email link | State-changing GET; partial-fatal path |
| `invited → confirmed` | Post-invitation management/legacy consent | Delivery and transition coupling |
| Applicant decline → `rejected` | Legacy consent | Conflates applicant response and reviewer decision |
| Arbitrary status replacement | Bulk administration | No transition policy/history |
| `confirmed → checkin` | Check-in save | Attendance event replaces lifecycle state |
| `finalize` plus alumni flags | Course bulk action | Completion and person eligibility coupled |
| Cancellation/leave aliases | Public link flows | Multiple route/result contracts |

Target state changes are commands to Application Workflow. Every successful change appends `application_status_events` with actor, reason, expected prior version, correlation, and source.

## 9. Cross-cutting outcome contract

Every redesigned page/flow must specify and test:

| Outcome | Minimum behavior |
|---|---|
| Loading | Preserve layout, announce progress, prevent duplicate destructive action |
| Empty | Explain why empty and show allowed next action |
| Validation | Thai-first field and summary errors; focus summary; keep entered data |
| Denied | No sensitive existence disclosure; safe recovery path |
| Expired/stale | Explain expiration/conflict; reload or restart without silent overwrite |
| Partial external failure | Commit through outbox or roll back; show truthful delivery state |
| Success | Receipt, current state, next step, timestamp, and audit correlation where appropriate |
| Offline/interrupted | Preserve acknowledged drafts; safe retry with idempotency |
| Unsupported artifact/device | Manual fallback and support instructions |

## 10. Flow change log template

Add new entries at the top:

| Date | Flow ID | Change type | Current evidence | Proposed behavior | Parity/retirement proof | Decision owner |
|---|---|---|---|---|---|---|
| `YYYY-MM-DD` | `FLOW-*` | discovered / corrected / proposed / approved / retired | file, route, runtime, or production evidence | concise behavior | test, telemetry, approval | role/name |

No current flow is considered removed by deleting code alone.
