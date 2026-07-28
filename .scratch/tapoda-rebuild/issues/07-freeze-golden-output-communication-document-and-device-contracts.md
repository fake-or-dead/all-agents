# Freeze golden output, communication, document, and device contracts

Status: `ready-for-human`

## What to build

Produce approved fixtures and matrices for every report, export, print, email, document, public link, and Thai ID companion behavior. Resolve current screen/export differences intentionally instead of copying contradictions.

## Acceptance criteria

- [x] Locally satisfied: applicant, teacher, laundry, print, and eight-sheet export fixtures define fields, grouping, membership, ordering, labels, dates, and authorized audiences.
- [x] Locally satisfied: screen, counter, print, and export differences are reconciled by one local `ReportSpecification`.
- [x] Locally satisfied: every notification variant defines recipient, audience, course, template, link, attachment, sender, retry, bounce, and failure behavior through a deterministic fake gateway.
- [x] Locally satisfied: `CLARIFY-005` defaults to disabled unresolved operational template; schedule/manual reminders use one idempotent policy.
- [x] Locally satisfied: document metadata and safe local compatibility-URL disposition are defined.
- [x] Locally satisfied: Thai ID fake/secure interface, minimum fields, trust model, mismatch default, health/version behavior, and manual fallback are defined.
- [x] Locally satisfied: local brand/accessibility, email, and print defaults are defined.
- [ ] Production signoff required: public PDFs, course attachments, consent documents, compatibility URLs, checksums, versions, visibility, locale, and retention rules.
- [ ] Production signoff required: Thai ID supported operating systems, packaging, signing, update/rollback, and exact mismatch policy.
- [ ] Production signoff required: official names, logo, palette acceptance, email-client and print support matrices, and Operations Gate G6 approval.

## Local completion record

2026-07-29 — [Local output, communication, document, and device contracts](../../../docs/decisions/local/output-device-contracts.md), [synthetic fixture](../../../docs/decisions/local/output-device-fixtures.json), and [deterministic validator](../../../docs/decisions/local/validate-local-contract-fixtures.mjs) define executable local defaults and exclude owner signoff.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
- [04 Approve compatibility and coexistence contracts](./04-approve-compatibility-and-coexistence-contracts.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
