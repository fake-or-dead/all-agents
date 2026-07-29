# Identity and Access

Issue #11 introduces the first account journey behind one module interface:
`IdentityAccessWorkflow`.

## Owned state

- People owns `people` and `person_identifiers`. Identity registration calls
  `PersonIdentityDirectory` inside the shared application transaction rather
  than writing People tables. Identifiers use encrypted ciphertext, a
  separately keyed HMAC lookup, type, country code, and masked last four.
- `accounts` stores account state, a keyed email digest for lookup, and the
  encrypted email value.
- `credentials` separates password hashes from accounts and records whether a
  supported legacy bcrypt hash still needs controlled rehashing.
- `verification_challenges` stores only hashes/digests for expiring
  verification, registration-proof, and recovery secrets.
- `consent_acceptances` is append-only evidence for the exact registration
  consent version.
- `auth_sessions` is the account-session revocation ledger.

## Security behavior

- Verification requests, recovery requests, and sign-in failures use neutral
  responses. Rate limits are scoped by client address and a one-way identifier
  hash.
- Resending invalidates previous live challenges. Verification enforces expiry,
  attempts, and one-use registration proof redemption under database locks.
- Registration coordinates Person, identifier, Account, credential, consent
  receipt, challenge consumption, and audit evidence in one transaction.
- Missing and unsupported accounts perform the same expensive password
  verification as known accounts. Known and unknown recovery requests persist
  the same challenge/delivery shape before returning the same public response.
- Login rotates the framework session. Successful supported legacy bcrypt login
  immediately replaces the old hash. Unsupported hashes fail neutrally and use
  recovery.
- Logout, password change, and recovery redemption are POST-only web routes.
  Laravel web request-forgery protection rejects cross-site state changes.
- Recovery never changes the credential until a valid one-use token is
  redeemed. Redemption revokes every live account session.
- Audit context contains outcome codes and consent versions, never email,
  identity number, name, password, verification code, or recovery token.

## Local verification adapter

Local and test runtime uses `DeterministicFakeVerificationGateway`. It performs
no network request. The fixed local verification code is configured by
`IDENTITY_DETERMINISTIC_CODE`. Recovery deliveries are encrypted in the local
database so they survive separate PHP requests. Browser acceptance obtains its
own recovery path through `php artisan identity:local-recovery-path` using
stdin. There is no HTTP mailbox endpoint: ordinary HTTP requests return 404,
and recipient or bearer values never enter a query URL. `/forgot` never
returns the token.

No production email or OTP provider is enabled by this issue.

## Verification

Focused backend:

```sh
php vendor/bin/phpunit tests/Feature/IdentityAccess
```

Focused browser acceptance against the local Compose runtime:

```sh
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 \
  npx playwright test tests/Browser/auth.spec.ts
```
