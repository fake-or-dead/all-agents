# Tapoda Coverage and Parity Matrix

**Status:** Reconciliation register

**Repository baseline:** `uat-20260526` at `3d2c3a4`

This matrix prevents a route, page, branch, side effect, report, document, or dormant artifact from disappearing during the rebuild. It connects current evidence to a flow, target module, and proof.

## 1. Reconciled repository totals

| Surface | Repository total | Logged | Reconciliation source | Status |
|---|---:|---:|---|---|
| Concrete web route declarations | 99 | 99 | `current-page-inventory.csv` IDs `W001-W099`; runtime conditional for local-gated and missing actions | complete-static |
| Concrete API route declarations | 10 | 10 | `current-page-inventory.csv` IDs `A001-A010` | complete-static |
| Commented endpoint | 1 | 1 | `/signin` in `routes/web.php` | classified dormant |
| Blade route pages | 48 | 48 | `current-page-inventory.md` section 6.1 | complete-static |
| Blade layouts | 4 | 4 | `current-page-inventory.md` section 6.2 | complete-static |
| Active Blade partials/components | 14 | 14 | `current-page-inventory.md` section 6.3 | complete-static |
| Active print/report helpers | 6 | 6 | `current-page-inventory.md` section 6.4 | complete-static |
| Active email templates | 13 | 13 | `current-page-inventory.md` section 6.5 | complete-static |
| Orphan/unknown Blade files | 6 | 6 | `current-page-inventory.md` section 6.6 | classified |
| View README | 1 | 1 | `resources/views/apply/v2/README.md` | non-view documented |
| Root static HTML artifacts | 8 | 8 | `current-page-inventory.md` section 8 | verified-artifact |
| Root PHP artifacts | 2 | 2 | `current-page-inventory.md` section 8 | verified-artifact |
| Public PDF assets | 12 | 12 | `current-page-inventory.md` section 8 | verified-artifact |
| Controllers | 22 | 22 | `app/Http/Controllers` | complete-static |
| Models | 21 | 21 | `app/Models` | complete-static |
| Business classes | 10 | 10 | `app/Business` | complete-static |
| Provider/integration services | 2 | 2 | `app/Services` | complete-static |
| Command classes | 2 | 2 | `app/Console/Commands` | logged; schedule status required |
| Change/runbook SQL files | 19 | 19 | `db_scripts` inventory | migration evidence only |
| Root reference SQL datasets | 2 | 2 | `country.sql`, `thailand.sql` | data-reference evidence only |
| Test cases | 10 | 10 | `tests/**/*Test.php` | coverage-quality gap logged |
| Test support files | 2 | 2 | `tests/TestCase.php`, `tests/CreatesApplication.php` | complete-static |

`complete-static` means repository files were reconciled. It does not prove deployed use, production schema, data quality, permissions, hardware, links, or operator workarounds.

## 2. Endpoint-to-flow reconciliation

| Endpoint IDs | Current page/interaction family | Flow IDs | Target module/interface | Required parity proof |
|---|---|---|---|---|
| `W001`, `W004-W005` | Stock/debug/public mail trigger | `FLOW-DEBUG-01` | Platform Operations | Production-usage check; authenticated diagnostic replacement; hard-coded PII removal |
| `W002-W003`, `W006-W008`, `W022` | Public catalog/content/course detail | `FLOW-PUB-01` | Course Catalog & Sessions | Filters, eligibility, empty results, content, attachments, responsive snapshots |
| `W009-W010` | Mail/debug previews | `FLOW-NOTIFY-01` | Notifications | Local-only access and template fixture coverage |
| `W011`, `W014`, `A003-A007` | Signup, agreement, OTP, identity availability | `FLOW-AUTH-01` | Identity & Access; Documents & Consent | Registration challenge matrix; consent receipt; non-enumeration |
| `W012-W013` | Applicant authentication | `FLOW-AUTH-02` | Identity & Access | Session, throttle, denied, logout |
| `W015`, `W018` | Password recovery | `FLOW-AUTH-03` | Identity & Access | No plaintext or enumeration; expiry/replay |
| `W016-W017` | Generic outcome pages | `FLOW-MSG-01`, `FLOW-INV-02` | Design System; Legacy Compatibility | Safe message-code mapping, success/error/expired layouts |
| `W019-W020` | Address lookup | `FLOW-REF-01` | Reference Data | Parent/child lookup contract |
| `W021`, `W027-W028`, `W045` | Application entry/resume | `FLOW-APP-01` | Application Workflow | Token/variant/step/idempotency matrix |
| `W023-W026` | Public confirm/cancel links | `FLOW-INV-02` | Invitations & Confirmations | Preview-before-POST, token replay/expiry, atomicity |
| `W029-W041` | Guided forms | `FLOW-APP-02` through `FLOW-APP-05` | Application Workflow; Form Engine | Four variants, all conditions, answer persistence, submission snapshot |
| `W042-W044` | Member hub | `FLOW-MEMBER-01` | People & Profiles; Application Workflow; Identity & Access | Tabs, history, own-record access, profile/password changes |
| `W046-W051` | Legacy application saves/pages | `FLOW-APP-06` | Legacy Compatibility | Captured payload translation and authorization |
| `W052-W062` | Legacy acceptance | `FLOW-INV-01` | Legacy Compatibility; Invitations & Confirmations | Five-step sequence, accept/decline, done-page replacement |
| `W063`, `W091-W092`, `W098` | Backend auth/landing | `FLOW-ADMIN-01` | Identity & Access; Design System | RBAC and workspace landing |
| `W064-W067` | Review/selection | `FLOW-REVIEW-01` | Review & Selection | Queue membership, immutable per-course review history |
| `W068-W070`, `W073`, `W075`, `A009` | Course operations | `FLOW-COURSE-01` | Course Catalog & Sessions; Application Workflow | Bulk preview/results, transition policy, accurate counters |
| `W071-W072` | Laundry UI/export | `FLOW-OPS-01` | Operations & Facilities; Reports & Exports | Screen/export parity and persisted operational data |
| `W074`, `W099` | Confirmation reminders | `FLOW-INV-03` | Invitations & Confirmations; Notifications | Due/cooldown/idempotency/provider retry |
| `W076-W081`, `A010` | Backend-account management | `FLOW-ADMIN-02` | Identity & Access | Scoped CRUD/reset/deactivation guards |
| `W082-W083` | Parole pages | `FLOW-PAROLE-01` | Owner unknown | Product/telemetry decision before design |
| `W084`, `W093-W097`, `A008` | Check-in | `FLOW-CHECKIN-01` | Check-in & Attendance | Course scope, duplicate scan, manual/card paths, event history |
| `W085` | Summary placeholder | `FLOW-DEBUG-01` | Reports & Exports or retirement | Owner-approved projection or removal |
| `W086-W087` | Applicant report/export | `FLOW-REPORT-01` | Reports & Exports | Eight groups/sheets share one projection |
| `W088-W090` | Teacher report/modal/print | `FLOW-REPORT-02` | Reports & Exports | Five groups, selection limit, modal/print fields |
| `A001` | API test | `FLOW-DEBUG-01` | Platform Operations | Liveness/readiness replacement |
| `A002` | Authenticated API user | `FLOW-AUTH-02` | Identity & Access | Sanctum identity/access contract |

### 2.1 Non-HTTP entry points

| Entry | Current effect | Flow ID | Target/disposition | Required proof |
|---|---|---|---|---|
| `request:confirm` | Finds at most one due approved trainee, sends request-confirm mail, then sets `is_request_confirm`; scheduler disabled | `FLOW-MAINT-01` | Invitations & Confirmations policy plus Notifications outbox | Production schedule/manual use, idempotency, delivery, and duplicate-run fixtures |
| `invite:accept {apply_id}` | Ignores argument, appends timestamp to `newfile.txt`, reports success; intended DB logic commented; scheduler disabled | `FLOW-MAINT-01` | Remove after usage check or replace through Legacy Compatibility command adapter | Cron/process telemetry, no file-write regression, owner-approved disposition |

## 3. Page-family migration matrix

| Current page family | UX state to preserve | Proposed template/pattern | Owning module | Consulted interfaces | Migration gate |
|---|---|---|---|---|---|
| Public course discovery/detail | Search, filters, eligibility, attachments, course action | `PublicShell`, `CourseCatalog`, `CourseDetail` | Course Catalog & Sessions | Reference Data; Documents & Consent; Design System | Filter/eligibility/link and responsive parity |
| Public content/documents | Suggestion, qualification, about, legal/document access | `ContentPage`, `DocumentLink` | Documents & Consent | Design System | Copy, URL, version, and retention mapping |
| Registration/authentication/recovery | OTP, consent, login, logout, recovery | `AuthShell`, `RegistrationFlow`, `RecoveryForm` | Identity & Access | People & Profiles; Documents & Consent; Design System | One responsive implementation; provider/error/security matrix |
| Guided application | Four ordered variants and conditional sections | `ApplicationShell`, `FormSection`, `StepNavigation`, `SubmissionReview` | Application Workflow | Form Engine; People & Profiles; Documents & Consent; Design System | Form definition, draft, submission, and scenario fixtures |
| Legacy application | Active token/payload/redirect behavior | Compatibility entry into the new renderer | Legacy Compatibility | Application Workflow; Form Engine; Design System | Active-link/payload telemetry and authorization proof |
| Legacy acceptance | Five-step response, consent, accept/decline, missing done | Compatibility entry into post-invitation flow | Legacy Compatibility | Invitations & Confirmations; Application Workflow; Design System | Accept/decline parity and stale route repair |
| Member hub | Profile, history, applications, password | `MemberShell` with owned panels | People & Profiles | Application Workflow; Identity & Access; Design System | Tab URL, own-record, empty/history/security states |
| Backend shell | Navigation, scoped session, task entry | `WorkspaceShell`, `TaskInbox` | Design System | Identity & Access; module projections | RBAC, landmarks, navigation, responsive fixtures |
| Review | Segmented queues, dossier, decision | `ReviewQueue`, `ApplicantReview`, `DecisionPanel` | Review & Selection | Application Workflow; People & Profiles; Design System | Per-course immutable review and decision audit |
| Course configuration | Course list, registration policy, session settings | `CourseWorkspace`, `SessionSettings` | Course Catalog & Sessions | Design System | Policy, capacity, setting, and scope fixtures |
| Bulk applicant transitions | Preview and per-person transition result | `BulkActionPreview`, `BulkActionResult` | Application Workflow | Course Catalog & Sessions; Audit & Compliance; Design System | Allowed/denied/mixed-result matrix |
| Laundry/facilities | Room, day 01–08, costs, needs, adjustments | `OperationsGrid` | Operations & Facilities | Reports & Exports; Design System | Persisted facts and screen/export parity |
| Check-in | Scoped login, search, verify, arrival | `CheckInShell`, `ParticipantLookup`, `IdentityComparison` | Check-in & Attendance | Identity & Access; Application Workflow; Design System | PII scope, manual/card, mismatch, idempotent event proof |
| Backend accounts | List, add, edit, deactivate, recover, scope | `AdminAccounts` | Identity & Access | Audit & Compliance; Design System | Permission and destructive-action guards |
| Parole pages | Current miswired list/detail behavior only | No new page until purpose decision | Legacy Compatibility | Identity & Access; Design System | Owner, data, permission, telemetry decision |
| Applicant report/export | Eight groups/sheets and pinned identity | `ReportFilter`, `AccessibleDataGrid`, queued export | Reports & Exports | Application Workflow; Design System | One projection and golden output equality |
| Teacher report/modal/print | Five groups, detail, max-ten selection | `TeacherQueue`, `ParticipantDialog`, `PrintSelection` | Reports & Exports | Review & Selection; Design System | Field policy, selection, and print fixtures |
| Generic legacy result | Safe message, error/expired recovery | `ResultPage` variants | Legacy Compatibility | Design System | Signed code-to-copy mapping |
| Confirmation outcomes | Confirm, cancel, decline, withdraw receipt/next action | `ResultPage`, `ConfirmationReceipt` | Invitations & Confirmations | Application Workflow; Design System | Token and outcome parity |
| Email | Audience/course/template/link/attachment variants | Generated email recipes | Notifications | Documents & Consent; Design System | Snapshot/provider/delivery matrix |
| Root HTML/PHP artifacts | Unknown prototype/diagnostic use | Migrate, archive, or remove | Legacy Compatibility | Platform Operations | Telemetry, security, owner, retention, rollback |
| Public PDFs | Current/historical documents and attachments | Versioned document library | Documents & Consent | Notifications; Legacy Compatibility | URL, checksum, version, retention, redirect |

## 4. Target module traceability

| Module | Current evidence assigned | State it owns in the new system | Interfaces required by page/flow |
|---|---|---|---|
| Identity & Access | Applicant/admin/check-in login, signup, recovery, backend users | identities, credentials, challenges, sessions, roles, scopes | authenticate, register, recover, authorize, manage account |
| People & Profiles | `users`, `contact`, member/profile sections | person, current profile, contacts, eligibility facts | view/update profile, resolve person |
| Reference Data | Address and master lookups | versioned reference records | address/options queries |
| Course Catalog & Sessions | Public list/detail, backend course pages | course definition, session, center, policy, capacity | browse, resolve policy, manage session |
| Form Engine | Question groups, choices, answer rendering | form/version/section/field/condition/validation definitions and publication events | schema, validate, freeze answers, handle definition command |
| Application Workflow | Guided/legacy application and status writes | application, draft, immutable submission, status events | start, save draft, submit, transition, timeline |
| Review & Selection | Approval queues/detail/store | rounds, assignments, drafts, immutable reviews, scores, decisions/events | assign, save draft, submit review, decide, queue |
| Invitations & Confirmations | Accept, confirm/cancel links, reminder request | invitations, responses, action tokens, confirmation state | invite, respond, withdraw, resolve token |
| Check-in & Attendance | Course-password access, PII search, save | operator grant, attendance/check-in events | authorize station, find participant, record arrival |
| Operations & Facilities | Laundry/facility views/export | rooms, daily services, costs, needs, adjustments | manage operations, course worksheet |
| Notifications | 13 active templates and synchronous mail calls | notification intents, outbox, delivery attempts/events | enqueue, preview, delivery status |
| Reports & Exports | Applicant/teacher/laundry reports and files | report definitions, projections, export jobs/artifacts | query report, request/download export |
| Documents & Consent | PDFs, agreement/PDPA pages | document versions, consent requirements/receipts | publish, acknowledge, retrieve |
| Audit & Compliance | Missing/inconsistent logs across flows | append-only audit/security/access/export events | record/query authorized audit |
| Legacy Compatibility | Old routes/tokens/forms/static artifacts | mapping and compatibility records only | exchange token, translate payload, map response |
| Design System | Layouts, local CSS/JS, email/print presentation | tokens, primitives, patterns, templates, generated adapters | documented UI interface only |
| Platform Operations | Debug/health, jobs, observability, deploy artifacts | operational configuration and telemetry | health, job status, diagnostics |

`Course Workspace` composes module projections. It owns no lifecycle state.

## 5. Current-flow weakness register

| Gap ID | Current evidence | Risk | Required replacement or decision | Blocking gate |
|---|---|---|---|---|
| `G-ROUTE-001` | `/accept/done` targets missing action | Broken terminal flow | Implement mapped Result Page or compatibility redirect | G1 route/runtime capture |
| `G-ROUTE-002` | Duplicate `apply.v2.profile` route name | Ambiguous URL generation | Unique route names with compatibility tests | Routing contract |
| `G-ROUTE-003` | Stale `/accept/save-apply-question` AJAX | Client error/duplicate logic | Remove after active-flow capture and replacement | Browser/runtime trace |
| `G-TXN-001` | Confirm writes state before undeclared dependency fatal | Partial transition | Transactional command + outbox + regression test | Critical before migration |
| `G-AUTH-001` | Save actions accept client application/state identifiers | Cross-account/state tampering | Server-resolved aggregate and authorization | Security gate |
| `G-AUTH-002` | Global CSRF exemption | Cross-site mutation | Standard CSRF and explicit machine auth | Security gate |
| `G-AUTH-003` | Public check-in PII lookup | Personal-data disclosure | Course-scoped operator authorization, minimum fields | Critical |
| `G-AUTH-004` | Public identity/username existence checks | Enumeration | Neutral validation response and throttling | Security gate |
| `G-CRED-001` | Forgot password overwrites/emails plaintext | Account takeover/secret disclosure | One-use reset challenge | Critical |
| `G-HTTP-001` | Confirm/cancel/delete/logout mutate by GET | Link crawler/CSRF side effects | GET preview + POST/DELETE action | Compatibility gate |
| `G-STATE-001` | Arbitrary bulk status replacement | Invalid lifecycle and no history | State-machine bulk command with preview/reason/version | Domain gate |
| `G-STATE-002` | Check-in replaces application state | Lost lifecycle meaning | Append attendance event | Domain gate |
| `G-STATE-003` | Applicant decline and reviewer rejection both `rejected` | Incorrect explanation/reporting | Separate states/events | Domain gate |
| `G-REVIEW-001` | Only latest mutable application outcome is explainable | Cannot track customer review per course/submission | Per-course immutable review rounds, reviews, decisions, events | Core requirement |
| `G-FORM-001` | Hard-coded question/group IDs and page-local validation | Fragile changes | Versioned Form Engine and schema validation | Form migration gate |
| `G-FORM-002` | Historical answer/profile snapshot absent | Later edits alter interpretation | Immutable submission snapshots | Data-model gate |
| `G-MAIL-001` | Synchronous send failure after mutation is swallowed | False delivery state | Transactional outbox, retry, delivery events | Integration gate |
| `G-SCHEDULE-001` | Reminder commands exist; schedule disabled | Missed follow-up | Approved scheduler policy and worker observability | Operations gate |
| `G-REPORT-001` | HTML/export status membership differs | Incorrect decisions/downloads | One projection/classification policy | Data reconciliation |
| `G-OPS-001` | Laundry UI/export membership differs; columns not persisted | Operational data loss | Persisted operations model and shared export | Owner decision |
| `G-PAROLE-001` | Parole pages appear wired to admin accounts | Unknown domain/privacy exposure | Owner/data/telemetry decision | Mandatory discovery |
| `G-UI-001` | Bootstrap 5 + embedded Bootstrap 4 + AdminLTE | Cascade collision | One Design System with legacy containment | UI foundation |
| `G-UI-002` | 231 inline styles; 43 style blocks; 149 script tags | Low locality and inconsistent state | Tokens, primitives, patterns, page templates | UI migration |
| `G-UI-003` | Existing browns fail normal-text AA | Accessibility failure | Approved accessible semantic palette | Brand/accessibility |
| `G-UI-004` | Fixed 960/1800/2980 px layouts and UA branches | Poor responsive behavior | Container/grid templates and capability CSS | Responsive gate |
| `G-UI-005` | `lang="en"` and zoom disabled | Thai/a11y defect | `lang="th"`, zoom allowed, reflow tests | Accessibility gate |
| `G-TEST-001` | Tests largely inspect source strings | Behavior regressions | Domain, contract, integration, browser, a11y, visual suites | CI gate |
| `G-DATA-001` | Migrations do not recreate deployed database | Migration uncertainty | Production DDL/profile/reconciliation | G2 data gate |
| `G-ART-001` | Orphan/static/PDF use unknown | Accidental feature/link loss | Telemetry, owner, retention, rollback disposition | Retirement gate |

## 6. CSS and Corporate Identity reconciliation

| Current surface | Evidence | New single-system control | CI enforcement |
|---|---|---|---|
| Public/applicant | Bootstrap 5.1.3 plus full Bootstrap 4.3.1 in `style.css`, local CSS/JS | Semantic tokens, `PublicShell`, application form patterns | Reject framework import and raw token values |
| Backend | AdminLTE/Bootstrap 4 and page styles | `WorkspaceShell`, data grid, filters, dialog, bulk action patterns | Component accessibility and screenshot matrix |
| Messages | Mixed public/AdminLTE/framework generations | One `ResultPage` pattern | Route-state snapshots |
| Email | Inline client-specific styles | Generated Email adapter from canonical tokens | Email snapshots and client lint |
| Print/report | Page-local widths/sticky/print CSS | Print adapter and accessible Data Grid | Print PDF and wide-table visual baselines |
| Legacy during migration | Global cascade | `[data-ui-generation="legacy"]` containment | No selector leakage into `next` root |

Canonical detail: [`ci-design-system.md`](ci-design-system.md).

## 7. Production discovery gates

| Gate | Evidence required | Output | Blocks |
|---|---|---|---|
| `G1` Runtime route/page capture | Deployed route list, status/redirect, auth, screenshots, active links | Verified current behavior catalog | Route retirement and compatibility |
| `G2` Database DDL/profile | Schema, row counts, null/enums, duplicates, relationships, sample-safe distributions | Migration mapping and anomaly policy | Final schema/migration |
| `G3` Permission observation | Roles, course/center scope, exported fields, support access | Approved RBAC/field policy | Staff UI and reports |
| `G4` Communication inventory | Provider, sender domains, templates, active links, attachments, delivery/bounce behavior | Notification compatibility matrix | Email cutover |
| `G5` Scheduled/manual operations | Cron, commands, spreadsheets, reminders, workarounds, ownership | Operations runbook and automation scope | Worker cutover |
| `G6` Thai ID companion | Installer, endpoint/version, supported OS, fields, trust/timeout/mismatch behavior | Secure optional adapter contract | Card-assisted check-in |
| `G7` Brand/content/legal | Logo, palette approval, Thai copy, document versions, consent/legal retention | Approved token/content/document set | UI/content launch |
| `G8` Traffic/artifact telemetry | Route/PDF/static/link access and referrers | Migrate/archive/remove decisions | Legacy retirement |

## 8. Definition of coverage complete

The rebuild can claim current-flow coverage only when:

1. Every `W001-W099` and `A001-A010` has a flow and disposition.
2. Every 91 Blade file, 8 root HTML file, 2 root PHP artifact, and 12 PDF asset has a disposition.
3. Every current state write maps to an authorized transition or append-only event.
4. Every email, export, print, scheduled command, and device interaction has a success/failure/retry contract.
5. Every page template has loading, empty, validation, denied, expired/stale, partial failure, and success states where applicable.
6. Form parity covers all four guided variants plus legacy payload compatibility.
7. Review history is immutable and separately explainable per course, submission, round, reviewer, and decision.
8. Applicant, teacher, and laundry screen/export classifications reconcile against one approved fixture set.
9. The Design System is the only new source of visual tokens and interaction primitives.
10. Production gates either pass or have an explicit owner-approved exception with expiry and rollback.
