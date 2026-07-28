# Local RBAC and privacy contract

Status: locally executable default. This is not production approval, legal advice, or Product/Privacy owner signoff.

## Scope and enforcement

Every staff request must pass all four checks: authenticated `account`, active `role_assignment`, requested `action` on `resource`, and matching `center_id` plus `course_session_id` scope. Missing scope denies. Person-owned routes also require `person_id == authenticated_person_id`. Workers use a separate machine identity and never inherit a human role.

| Actor | Locally allowed actions and scope | Denied by default |
|---|---|---|
| Visitor | Read public catalog, published public documents | Accounts, applications, person data, private documents |
| Applicant / alumni / staff applicant | Own account, profile, drafts, submissions, invitations and history; current session only | Other people, staff workspace, reports |
| Course staff | Own staff application; assigned duties in assigned session | Review decisions, roles, export |
| Reviewer | Read assigned-session submissions; submit review | Decision, exports, unassigned sessions |
| Course manager | Configure and operate assigned center/session; lifecycle commands | Sensitive field read unless separately granted; global administration |
| Teacher reviewer | Read approved teacher projection and print for assigned session | National ID; export; raw mental-health/substance-use data |
| Check-in operator | Search confirmed participants; record check-in and manual verification in assigned session | Reports, exports, health detail, national ID display |
| Operations | Attendance, room, dinner, seating, laundry, facility needs in assigned session | Review, account roles, health detail, export |
| Reporting user | Request approved report/export in assigned session with explicit field grants | Unapproved fields, other sessions |
| Support | Case-bound account/link recovery assistance only | Content browsing, exports, role assignment, sensitive fields |
| Administrator | Account, role, scope, configuration administration | Self-approval; self-disable; last-admin disable; sensitive access without field grant |
| System worker | Deliver outbox messages, generate authorized export artifacts, enforce schedules | Interactive login, arbitrary queries, human impersonation |

`manager`, `reviewer`, `teacher`, `check_in`, `operations`, and `reporting` assignments require both center and course-session scope. An account can receive more than one assignment; permissions union only within each matching scope. Cross-center/global scope is unavailable locally except a separately assigned administrator action.

## Field policy

| Field class | Read / print / export grant | Local rule |
|---|---|---|
| Normal identity, application, training, attendance | `person.read`, scoped role | Minimum fields for task; no bulk download without export grant |
| Emergency contact | `emergency_contact.read` | Teacher print and operations only where needed; export requires `emergency_contact.export` |
| Health and medication | `health.read` | Teacher may view/print only approved session projection; operations/check-in receive a safe operational flag, never free text |
| Mental health and substance use | `mental_health.read`, `substance_use.read` | Reviewer only when assigned and explicitly granted; never teacher/check-in/operations defaults; independent export grant required |
| National ID | `national_id.lookup` or `national_id.read` | Lookup uses keyed HMAC; display is masked except last four digits. Full read is prohibited locally; no print/export grant exists |
| Report / print / export | `report.read`, `print.create`, `export.request` plus each included field grant | Export uses server-authorized fields, not visible UI columns; all results are audited |

Sensitive data never enters URLs, analytics, cache keys, notifications, browser logs, queue payloads, or test fixtures. National ID is encrypted at rest and exact lookup uses a separately keyed HMAC. Local fixtures use synthetic values only.

## Guardrails, audit, and retention

- Support grant requires `case_reference`, purpose, target account, approver distinct from grantee, expiry no later than 8 hours, and automatic revocation. Support cannot approve or renew its own grant.
- Role grants require reason, grantor, scope, validity window, and audit event. Administrator cannot grant/approve their own privileged role.
- Self-disable is denied. Disabling or narrowing the final active administrator is denied. Recovery resets credentials through one-use, expiring flow; it never reveals existing secrets.
- Record sensitive reads, masked-ID lookup, support grants/revocations, role changes, lifecycle transitions, document access, print creation, export request/generation/download, and worker delivery attempts in append-only audit/data-access events. Include actor or machine identity, target class/ID, center/session, action, outcome, reason/case where required, correlation ID, and timestamp. Do not record field values.
- Local retention: audit and access events 90 days; support grants 90 days after expiry; notification attempt metadata 30 days; local export/print artifacts 24 hours; local documents/fixtures for the development run only. A scheduled purge records counts and correlation IDs, not deleted values.
- Correction/deletion requests create auditable requests. Local implementation may hard-delete synthetic fixture data after retention; production legal basis, legal holds, retention periods, correction/deletion workflow, breach response, and PDPA signoff remain excluded.

## Local acceptance tests

1. Attempt every staff action with wrong center or session: deny and audit.
2. Attempt every sensitive field without its field grant: omit/redact and audit denied access.
3. Attempt support without a case, expired grant, or self-approval: deny.
4. Attempt self-disable, final-admin disable, privileged self-grant, and raw-ID export: deny.
5. Assert export artifact includes only authorized fields and expires at 24 hours.

## Production owner/signoff exclusions

Product and Privacy owners must approve the role matrix, field necessity, legal basis, consent, retention, correction/deletion, breach handling, audit retention, and any exception before G4/G7 may pass. This document deliberately supplies local defaults only.
