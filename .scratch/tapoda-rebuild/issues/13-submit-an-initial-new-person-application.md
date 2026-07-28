# Submit an initial new-person application

Status: `ready-for-agent`

## What to build

Deliver the first complete application tracer: an eligible new person starts an application explicitly, resumes it safely, completes profile, preferences, and consent, autosaves semantic answers, submits an immutable revision, and sees a receipt and lifecycle timeline.

## Acceptance criteria

- [ ] Starting uses an authorized idempotent command; viewing or resuming never creates or transitions an application.
- [ ] The `pre-new` form schema is selected from server-derived context and rendered through one accessible question-section registry.
- [ ] Autosave uses semantic keys, optimistic revision control, idempotency, server validation, and the published hidden-answer policy.
- [ ] Navigation resumes at the last valid step and preserves data across refresh, interruption, validation failure, and stale revision conflicts.
- [ ] Submission atomically freezes profile, answers, labels, form version, consent, and applicable typed data into an immutable submission.
- [ ] Only Application Workflow performs the `draft → submitted` transition and appends status, audit, and outbox events.
- [ ] The receipt and timeline show the submitted revision, state, timestamp, next action, and communication status.
- [ ] `FLOW-APP-01`, `FLOW-APP-02`, security, accessibility, responsive, and historical-immutability tests pass.

## Blocked by

- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
- [09 Bootstrap one deployable audited platform path](./09-bootstrap-one-deployable-audited-platform-path.md)
- [10 Browse and inspect an eligible course session](./10-browse-and-inspect-an-eligible-course-session.md)
- [11 Register, verify, sign in, recover, and secure an account](./11-register-verify-sign-in-recover-and-secure-an-account.md)
- [12 Manage profile, training history, applications, and security](./12-manage-profile-training-history-applications-and-security.md)
