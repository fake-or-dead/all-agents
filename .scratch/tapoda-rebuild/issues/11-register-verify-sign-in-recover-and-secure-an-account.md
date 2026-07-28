# Register, verify, sign in, recover, and secure an account

Status: `ready-for-agent`

## What to build

Give a person one secure responsive account journey: verify an email, accept the current consent version, register, sign in, sign out, recover access, and preserve supported legacy password access through controlled rehashing.

## Acceptance criteria

- [ ] Registration supports the approved email verification method, personal or passport identity policy, profile minimums, versioned consent receipt, and atomic account/person creation.
- [ ] Verification challenges enforce expiry, attempts, resend invalidation, rate limits, one-use redemption, neutral responses, and deterministic provider tests.
- [ ] Sign-in rotates sessions, throttles failures, restores intended destinations, and never reveals account existence.
- [ ] Sign-out and all credential changes use state-changing methods with CSRF protection; no GET mutation remains.
- [ ] Recovery uses an expiring one-use challenge, never sends plaintext credentials, preserves the current credential until redemption, and revokes sessions after success.
- [ ] Supported legacy hashes rehash on successful login; unsupported hashes enter safe recovery.
- [ ] Security, ownership, audit, Thai-first responsive, accessibility, and `FLOW-AUTH-01`–`FLOW-AUTH-03` tests pass.

## Blocked by

- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [09 Bootstrap one deployable audited platform path](./09-bootstrap-one-deployable-audited-platform-path.md)
