# Administer scoped staff accounts safely

Status: `ready-for-agent`

## What to build

Let an authorized system administrator list, create, edit, deactivate, recover, and scope staff accounts without exposing account existence or allowing unsafe self-lockout. Every privilege and credential action must be reasoned, authorized, and audited.

## Acceptance criteria

- [ ] Account administration supports create, edit, deactivate, safe recovery, role assignment, center/session scope, validity window, grantor, and reason.
- [ ] Duplicate username or identifier checks use neutral authorized validation and never expose public enumeration endpoints.
- [ ] Self-disable, last-administrator removal, unauthorized privilege grant, expired support access, and unapproved sensitive permission changes are blocked.
- [ ] Credential setup and recovery use invitation or one-use reset flows; no plaintext password appears in email, response, storage, browser state, or logs.
- [ ] Mutations use CSRF-protected state-changing methods with confirmation where destructive.
- [ ] Lists, details, audit, denied, stale, and success results expose only approved fields and scopes.
- [ ] `FLOW-ADMIN-02`, privilege-escalation, rate-limit, audit, and concurrency tests pass.

## Blocked by

- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [11 Register, verify, sign in, recover, and secure an account](./11-register-verify-sign-in-recover-and-secure-an-account.md)
- [20 Operate one course session from Course Workspace](./20-operate-one-course-session-from-course-workspace.md)
