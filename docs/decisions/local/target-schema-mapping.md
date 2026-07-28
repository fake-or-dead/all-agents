# Local target-schema mapping decision

**Decision ID:** `LOCAL-G1-SCHEMA`
**Applies to:** `G1`; dependent on production `G0` and lifecycle `G2`
**Source:** static repository evidence at `3d2c3a4b843f73cf0b19c5cb9a4d2e54e80d78aa`
**Status:** Local mapping contract accepted; live-column mapping and owner signoff excluded

## Invariants

- Every imported record has `source_system`, `source_table`, `legacy_id`, `migration_batch_id`, `source_checksum`, `confidence`, and `imported_at`.
- Canonical keys are ULID/UUID. A legacy identifier is traceability only, never a business key.
- Each migration is idempotent by `(source_system, source_table, legacy_id, migration_batch_id)` plus a content checksum. Changed source content creates a reconciliation record, not an overwrite.
- Invalid, contradictory, unresolved, or referentially incomplete data enters `migration_quarantine` with reason, severity, owner module, and source reference. Nothing is silently dropped or invented.
- Immutable submissions, answer snapshots, reviews, decisions, consent acceptances, action consumption, check-in events, and audit events are append-only. Mutable drafts/current profiles use optimistic `version` locking.
- A target module is the sole writer of its owned records. Compatibility adapters read legacy-shaped data; no bidirectional dual-write.

## Source-to-target contract

Exact columns await production `G0`; this is the static-table mapping used for local ETL fixtures.

| Static source table | Target owner | Target records | Transformation / quarantine rule | Flow IDs |
|---|---|---|---|---|
| `users`, `password_resets`, `personal_access_tokens` | Identity & Access | `accounts`, `credentials`, `verification_challenges`, `auth_sessions`, `roles`, `capabilities`, `account_roles` | Split credential from Person. Preserve supported hash only for controlled rehash; unsupported hash quarantines to recovery. | `FLOW-AUTH-01..03`, `FLOW-ADMIN-01..02` |
| `contact`, `training_history_info` | People & Profiles | `people`, `person_contacts`, `person_addresses`, `person_training_history` | Normalize references; retain raw legacy value beside canonical value for reconciliation. | `FLOW-MEMBER-01`, `FLOW-APP-01..05` |
| `amphoes`, `tambons`, `provinces`, `countries`, `prefixes`, `languages`, `education_level`, `trainee_type`, `tutor_type`, `custom_period_times` | Reference Data | versioned reference records | Preserve stable legacy key; missing/duplicate key quarantines. | `FLOW-PUB-01`, `FLOW-REF-01`, `FLOW-APP-01..05` |
| `center`, `course`, `course_type`, `teacher` | Course Catalog & Sessions | `centers`, `course_definitions`, `course_sessions`, `session_teachers`, `registration_policies` | Split course definition from scheduled course session where mixed; unresolved course/teacher link quarantines. | `FLOW-PUB-01`, `FLOW-COURSE-01` |
| `group_question`, `question`, `question_choices`, `question_has_group` | Form Engine | `form_definitions`, `form_versions`, `form_sections`, `form_fields`, `form_options`, `form_publication_events` | Numeric IDs become semantic keys; unassigned/ambiguous question or choice quarantines. Published version is immutable. | `FLOW-APP-02..05`, `FLOW-REPORT-01..02` |
| `apply_course`, `apply_course_manager`, `question_apply_course` | Application Workflow | `applications`, `application_drafts`, typed draft records, `application_submissions`, profile/manager snapshots, submission answers, `application_status_events` | Map legacy status using approved `G2` matrix. `accepted`, conflicting rejection/decline, and missing submission evidence quarantine pending policy. | `FLOW-APP-01..06`, `FLOW-REVIEW-01`, `FLOW-COURSE-01` |
| `apply_course_confirm`, `invite_accept` | Invitations & Confirmations | `invitations`, `invitation_actions`, `confirmation_responses`, `withdrawals` | Validate action/response ordering; token plaintext never migrates. Contradictory response/time data quarantines. | `FLOW-INV-01..03`, `FLOW-MAINT-01` |
| `checkins` | Check-in & Attendance | `checkin_events`, `identity_verifications`, `attendance_records` | Append source event; duplicate/reordered event becomes reconciliation item, not a lifecycle overwrite. | `FLOW-CHECKIN-01` |
| facilities/laundry facts embedded in answer, application, or check-in data | Operations & Facilities | `room_assignments`, `facility_requests`, `participant_service_entries`, `participant_purchases`, `operation_cost_adjustments` | Extract only semantically mapped values; unknown question meaning quarantines. | `FLOW-OPS-01` |
| mail templates/effects and delivery-related source evidence | Notifications | `notification_requests`, `notification_attempts`, `notification_suppressions`, `outbox_events` | Historic delivery evidence is imported only if lawful and complete; no synthetic “sent” event. | `FLOW-NOTIFY-01`, `FLOW-INV-03` |
| PDFs, attachments, consent values | Documents & Consent | `documents`, `document_versions`, `consent_definitions`, `consent_versions`, `consent_acceptances` | Store checksum, visibility, purpose, locale, retention, compatibility URL. Missing file/checksum quarantines. | `FLOW-PUB-01`, `FLOW-APP-02..05`, `FLOW-ART-01` |
| report/export evidence | Reports & Exports | `report_jobs`, `report_artifacts`, versioned read projections | Rebuild from owned projections; legacy output is an oracle, never a writer. | `FLOW-REPORT-01..02` |
| `apply` and legacy route/payload records | Legacy Compatibility | read adapter, mapping/reconciliation and quarantine records | Read-only during coexistence; no new feature state. | `FLOW-APP-06`, `FLOW-INV-01`, `FLOW-MSG-01`, `FLOW-ART-01` |
| `failed_jobs`, `migrations` | Platform Operations | framework migration metadata, `jobs`, `failed_jobs`, `cache`, `cache_locks`, `sessions`, deployment metadata | Do not treat operational records as domain history. | `FLOW-MAINT-01`, `FLOW-DEBUG-01` |

## Constraints and migration order

1. Reference data.
2. Accounts, People, profiles, contacts, training.
3. Centers, courses, sessions, teachers, documents.
4. Form definitions and immutable versions.
5. Applications, drafts, submissions, snapshots, alumni-eligibility events.
6. Answers and semantic-key snapshots.
7. Reviews/decisions, invitations/action tokens.
8. Check-ins, operations, consent, notifications, report artifacts, lawful audits.

Required constraints: unique canonical ID; unique active account identifier under approved normalization; unique semantic key per form version; one-use token digest; application optimistic version; unique source identity; immutable row guards; foreign keys only to published identifiers; append-only event sequence per aggregate; and a quarantine row for every failed transform.

## Deterministic local verification

Run the fixture pack in `production-baseline.md` twice with fixed batch `local-20260729-a`. Assert per-table source/target counts, sorted source checksums, quarantine reason counts, state distribution, and report-projection checksums. Repeat with `local-20260729-b` after one controlled source correction: only that source record and dependent projection change.

Production-only exclusions: live columns/types/indexes/collations, data volumes, legal retention, exact uniqueness policy, G2 lifecycle choices, and architecture/data-owner approval. This does not satisfy production `G1`.
