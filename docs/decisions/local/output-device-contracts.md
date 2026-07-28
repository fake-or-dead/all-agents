# Local output, communication, document, and device contracts

Status: locally executable default. This is not Operations, Brand, Accessibility, Legal, or production-owner signoff.

## Reports, exports, and print

One versioned `ReportSpecification` owns screen, counter, print, and export membership. The executable synthetic specification is [output-device-fixtures.json](./output-device-fixtures.json), validated by [validate-local-contract-fixtures.mjs](./validate-local-contract-fixtures.mjs). Every fixture carries `spec_version`, `course_session_id`, `requester`, `authorization_result`, and `generated_at`. Inputs contain synthetic application, teacher-print, and laundry records. Expected results cover zero rows, every state/group boundary, Thai ordering/ties, teacher selections 0/1/10/11, pagination, laundry totals, and unauthorized/redacted/expired downloads.

| Output | Membership / ordering | Local audience and fields |
|---|---|---|
| Applicant report + eight-sheet XLSX | Eight sheets: alumni male, new male, alumni female, new female, monk, nun, staff male, staff female. Include submitted through completed states, grouped by approved persona/category; seniority then Thai name then immutable application ID. | Reporting user with `report.read` and per-field grants. XLSX mirrors screen rows/fields exactly. |
| Teacher report + print | Five approved teacher groups; invited, confirmed, checked-in, and completed participants only. Thai name then application ID. Maximum 10 selected sheets per print job. | Assigned teacher with `print.create`; emergency/health/medication only if separately granted; no national ID, mental-health, or substance-use fields. |
| Laundry / facilities | Confirmed, checked-in, and completed participants only; selected category then room, Thai name, application ID. Days `01`–`08`, costs, totals, and persisted needs. | Operations or reporting user with scope and field grants. Screen, print, XLSX use identical rows and totals. |

The legacy report/export disagreement is intentionally resolved by the above rules; it is not copied. Thai ordering executes NFC normalization, outer-space trimming, internal-space collapse, `Intl.Collator('th')` primary comparison, then immutable application ID. Dates accept `YYYY-MM-DD` and render `dd/MM/BBBB` with `+543`. The validator computes membership, counters, projections, value parity, sorting, redaction, dates, totals, pagination, and authorization from the inputs before comparing expected screen/print/XLSX results. Output request, artifact generation, print rendering, download, expiration, and failure are audited. XLSX neutralizes formula-like values. Print/download only receives a short-lived authorized artifact URL.

## Notifications and reminders

All variants create a versioned immutable notification intent through a transactional outbox, then deterministic local sender `tapoda-local-fake@invalid`. The fake records `queued`, `sent`, `failed`, `bounced`, and retry attempts; it never sends network email. A failure never advances lifecycle state or marks an application notified. Attachment disposition is explicit per variant: `none` means no attachment is rendered or sent; `approved-document-key` requires a versioned document key from the fixture.

| Variant | Recipient / course mapping | Required content and behavior |
|---|---|---|
| Welcome | New account owner; no course required | Account-safe welcome, support route; no password |
| Password recovery | Account owner; no course | One-use expiring recovery link |
| Invitation / staff invitation | Selected applicant / staff applicant; exact session | Versioned invitation, exact course/session, expiry, confirmation/cancellation links |
| Legacy confirmation D03 / D10 / staff | Confirmed participant or staff; exact session and legacy persona | Three separate versioned templates, course details, approved attachment map |
| Current confirmation D03 / D10 / monastic D10 / staff | Confirmed participant, monastic, or staff; exact session and current persona | Four separate versioned templates, course details, approved attachment map |
| Request confirmation / scheduled reminder | Invited participant; exact session | Confirmation and cancellation links, deadline, cooldown; due only by policy |
| Cancellation | Applicant; exact session | Cancellation receipt, next action/support route |

`CLARIFY-005` local default: “Email 2” is disabled and excluded from the active notification inventory. Its machine-readable disposition is `template_unresolved` with no recipient, sender, course mapping, template, links, or assets. Any legacy trigger emits an audited `template_unresolved` result; it cannot send or change lifecycle state.

Reminder default: manual preview is read-only and lists every due recipient in one selected session. Manual send and scheduled worker use the same policy: eligible `invited` recipients whose reminder due time has passed, one active invitation, no completed confirmation/cancellation, and no successful send within 24 hours. Each recipient gets an idempotency key; partial results are recorded individually; retries use bounded deterministic attempts (1, 5, 15 minutes). No command selects an arbitrary first course or first person.

## Documents and URLs

Each document has immutable object/version, checksum, title, purpose, locale, classification (`public`/`private`), visibility rule, compatibility URL disposition, owner, created time, and retention class. Public links contain no person data. Private documents and export artifacts use short-lived signed URLs after authorization; document downloads are audited.

Locally inventory these legacy document names as metadata only: `apply-form.pdf`, `applyform-for-board.pdf`, `applyform-for-long.pdf`, `guideline-registration-2025.pdf`, older guideline, `manual.pdf`, `new-privacy.pdf`, `privacy.pdf`, `practice-dhamma-worker.pdf`, `practice.pdf`, `training-intro.pdf`, and course attachment. Do not migrate bytes or claim active status. Compatibility URLs resolve only to a local safe placeholder/404 decision until production owner, usage telemetry, checksum, retention, and redirect plan are approved.

## Thai ID companion

`IdentityReader.read(challenge)` is the browser-facing local interface. The browser calls only a paired `127.0.0.1` companion after explicit operator action. The companion accepts a short-lived one-use signed challenge, validates browser origin/expiry/nonce, and returns a signed minimum-data assertion. Laravel verifies it through `IdentityVerificationWorkflow`; Laravel never calls loopback.

The server-issued challenge and companion assertion bind `iss`, `kid`, `jti`, `nonce`, `actor_account_id`, `session_id`, `action=identity.verify`, `course_session_id`, `aud=tapoda-thai-id-companion`, and exact `origin`. Server validation requires known issuer/key ID, signature, audience, action, origin, actor/session/session scope, expiry, and equality of assertion `jti`/`nonce` to the challenge. It atomically consumes `jti` and nonce before persisting a verification event; replay, unknown `kid`, issuer/audience/action/origin mismatch, actor/session mismatch, expired challenge, bad signature, and already-consumed values are rejected and audited without card data.

Approved assertion fields: card-present result, masked national-ID reference, Thai name, English name, date of birth, and gender. Address and photo are excluded. Raw card payload, `/debug`, unauthenticated `/read-card`, wildcard CORS, and remote listeners are prohibited. Health exposes only safe version, readiness, and supported-OS status; it reveals no card data. Mismatch uses an approved course policy: local default **warn, require operator reason, permit audited manual verification**. Card read is optional; manual verification remains available after unavailable, timeout, mismatch, or verification failure. Pairing, key rotation, and revocation are audited.

Local fake reader returns deterministic synthetic signed assertions, plus unavailable/timeout/mismatch/invalid-signature cases. It performs no PC/SC I/O. Production installer signing, macOS notarization, Windows packaging, OS support, update/rollback, key custody, device trust, and exact mismatch blocking policy require owner signoff.

## Brand and accessibility defaults

Use Thai-first `lang="th"`, self-hosted Sarabun with system fallback, 16px body text, 44px touch targets, WCAG 2.2 AA contrast, visible focus, labels/error summary, status text plus icon, zoom allowed, and semantic design tokens only. Local starting tokens: brand `#765B49`, brand strong `#594235`, primary action `#176B49`, link `#285C8C`, warning `#8A5700`, danger `#A32929`; automated contrast checks decide use. Email has plaintext alternative and email-safe generated inline styles. Print uses generated print tokens, no navigation/actions, and golden PDF comparison.

## Production owner/signoff exclusions

Operations must sign report membership, labels, grouping, dates, fixtures, reminder cadence, notification audience/copy/assets/sender, attachments, active links, and document disposition. Brand and Accessibility owners must approve official name/logo/palette and support matrices. Privacy/Legal must approve document visibility/retention and Thai-ID fields. None is approved by this local contract.
