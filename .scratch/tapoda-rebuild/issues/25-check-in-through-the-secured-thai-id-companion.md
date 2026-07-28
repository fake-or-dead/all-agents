# Check in through the secured Thai ID companion

Status: `ready-for-agent`

## What to build

Add optional Thai ID card-assisted verification to the manual check-in journey. Laravel issues a short-lived challenge, the browser explicitly calls a paired loopback companion, the companion returns a signed minimum-data assertion, and the server verifies it under approved mismatch policy.

## Acceptance criteria

- [ ] The companion binds only to `127.0.0.1`, validates approved origins, and exposes safe health and version responses.
- [ ] Every read requires explicit operator action plus an unexpired one-use challenge and nonce.
- [ ] Paired device keys support provisioning, signed assertions, verification, rotation, revocation, and recovery.
- [ ] Only approved identity fields are returned; raw card payload, address, and photo are excluded unless separately approved.
- [ ] The server verifies signature, challenge, origin context, expiry, nonce, operator, course session, and participant match before recording evidence.
- [ ] Approved Thai/English name, identifier, date, and category mismatches produce warn or block outcomes with audit.
- [ ] Signed Windows and notarized macOS packages support controlled installation, update, rollback, and version support.
- [ ] Device absent, unhealthy, denied, timed out, mismatched, or unsupported states preserve the audited manual path.
- [ ] Browser, companion, server, key-management, replay, CORS, and supported-OS contract tests pass.

## Blocked by

- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [08 Approve runtime, recovery, and cohort-cutover policy](./08-approve-runtime-recovery-and-cutover-policy.md)
- [24 Check in a participant manually](./24-check-in-a-participant-manually.md)
