# Browse and inspect an eligible course session

Status: `ready-for-agent`

## What to build

Let a visitor find a course session, apply shareable filters, inspect its center, dates, policy, availability, documents, and map link, and receive a truthful eligibility or unavailable result. The journey must remain crawlable and usable when client-side JavaScript fails.

## Acceptance criteria

- [ ] Year, month, course type, and center are server-owned GET filters with shareable URLs and correct empty states.
- [ ] Course detail shows dates, center, teachers, registration window, category policy, capacity/availability, attachment, and outbound map link.
- [ ] Eligibility covers age, approved category, applicant type, invite-only rules, and existing application state without silent defaults.
- [ ] Province, amphoe, tambon, and relevant reference queries return stable typed results for valid, missing, malformed, and unknown inputs.
- [ ] Public content pages and approved document links retain their required outcomes and compatibility URLs.
- [ ] Thai metadata, responsive layouts, graceful JavaScript failure, WCAG 2.2 AA, and the five required viewport widths pass.
- [ ] `FLOW-PUB-01`, `FLOW-REF-01`, and assigned endpoint dispositions have automated parity coverage.

## Blocked by

- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
- [04 Approve compatibility and coexistence contracts](./04-approve-compatibility-and-coexistence-contracts.md)
- [05 Approve scoped RBAC, sensitive fields, and privacy](./05-approve-rbac-sensitive-fields-and-privacy.md)
- [07 Freeze golden output, communication, document, and device contracts](./07-freeze-golden-output-communication-document-and-device-contracts.md)
- [09 Bootstrap one deployable audited platform path](./09-bootstrap-one-deployable-audited-platform-path.md)
