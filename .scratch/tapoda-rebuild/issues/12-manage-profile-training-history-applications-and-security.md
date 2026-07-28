# Manage profile, training history, applications, and security

Status: `ready-for-agent`

## What to build

Give an authenticated person a task-oriented member center for the current profile, contact and address details, training history, application timeline, resume actions, historical receipts, and password security. Current edits must not rewrite historical application evidence.

## Acceptance criteria

- [ ] A person can view and update only their current profile, address, contacts, approved identifiers, and training experiences.
- [ ] Thai dependent address selection validates stable reference keys and preserves entered data on failure.
- [ ] The application list shows course-session state, next task, deadline, last saved time, history, and an authorized resume action.
- [ ] Historical submissions, reviewed snapshots, labels, consent, and training evidence remain unchanged after current profile edits.
- [ ] Password change verifies the current credential, redacts all secrets and request data, rotates sessions as approved, and records an audit event.
- [ ] Empty, loading, validation, denied, stale, interrupted, and success states are accessible and Thai-first.
- [ ] Own-record authorization and `FLOW-MEMBER-01` parity scenarios pass.

## Blocked by

- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [11 Register, verify, sign in, recover, and secure an account](./11-register-verify-sign-in-recover-and-secure-an-account.md)
