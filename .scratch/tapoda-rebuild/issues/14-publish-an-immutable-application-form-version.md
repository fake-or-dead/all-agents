# Publish an immutable application form version

Status: `ready-for-agent`

## What to build

Let authorized form administrators clone a published form, edit a controlled draft, preview every approved persona, pass deterministic publication checks, obtain separate approval, publish an immutable version, and assign or roll back versions for future applications.

## Acceptance criteria

- [ ] An author can clone a published version and edit sections, semantic keys, labels, help, types, choices, validation, applicability, visibility, and hidden-answer policy under optimistic locking.
- [ ] Preview covers every approved persona, locale, conditional branch, and required responsive size without writing applicant data.
- [ ] Publication rejects duplicate keys, broken references, cycles, unreachable fields, required-hidden conflicts, missing translations, destructive choice changes, and missing report mappings.
- [ ] Separate author and approver permissions, reasons, checks, timestamps, and audit events are enforced.
- [ ] Publishing creates an immutable version; historical submissions never change.
- [ ] Rollback reassigns an older published version only to future applications.
- [ ] Server and client rule evaluators pass the same contract fixtures.

## Blocked by

- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
- [09 Bootstrap one deployable audited platform path](./09-bootstrap-one-deployable-audited-platform-path.md)
- [13 Submit an initial new-person application](./13-submit-an-initial-new-person-application.md)
