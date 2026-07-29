# Dual Review Protocol

Every issue branch targets `integration/local-complete`. Every issue is reviewed at one immutable head commit.

Review begins after dependency integration and the final local candidate gates.
Independently usable approved slices are released to `main` without waiting for
all 32 issues.

## Reviewer A: architecture and security

Inspect:

- Module depth, interface size, seam placement, and state ownership.
- Authorization, center/course scope, sensitive fields, and PII handling.
- Transaction, outbox, idempotency, concurrency, and audit behavior.
- Migration provenance, quarantine, compatibility, and removal criteria.
- Security acceptance for the affected path.

## Reviewer B: product and quality

Inspect:

- Issue acceptance and current-flow parity.
- Thai-first content, responsive behavior, keyboard access, and WCAG 2.2 AA.
- Loading, empty, validation, denied, stale, partial-failure, retry, and success states.
- Report, export, print, notification, document, and operational correctness.
- Test quality, local operability, and browser evidence.

## Finding format

Each finding contains:

- Severity: `Blocker`, `High`, `Medium`, or `Low`.
- Location or affected behavior.
- Evidence.
- Required correction.
- Verification that will prove the correction.

## Cross-review

After independent review, each reviewer classifies every finding in the other report:

- `agree`
- `challenge`
- `duplicate`
- `missed`

A challenge requires PRD, code, or test evidence. Unresolved Blocker or High disagreement prevents approval.

## Approval

Both reviewers inspect the same final head SHA and submit `APPROVE`.

Approval requires:

- Zero unresolved Blocker or High findings.
- No failed acceptance criterion.
- Required focused and affected-suite checks green.
- No unresolved review thread affecting behavior or safety.

Any new commit invalidates prior approval.
