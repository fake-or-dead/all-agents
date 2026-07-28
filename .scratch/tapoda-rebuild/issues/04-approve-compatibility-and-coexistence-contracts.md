# Approve compatibility and coexistence contracts

Status: `ready-for-human`

## What to build

Give every current endpoint, page, action link, document, report, command, and artifact an approved target disposition. Define safe adapters and redirects, ownership during coexistence, usage telemetry, expiry, rollback, and removal criteria.

## Acceptance criteria

- [ ] All `W001-W099`, `A001-A010`, Blade files, root HTML/PHP artifacts, public PDFs, commands, and active email links have `migrate`, `adapter`, `redirect`, `archive`, or `remove` outcomes.
- [ ] Legacy application, acceptance, token, password-hash, payload, message, document-URL, and table-read compatibility contracts are explicit.
- [ ] Accounts, people, profiles, training, reference data, applications, reviews, invitations, and documents have one writer per migration phase.
- [ ] Parole pages, placeholder routes, orphan code, debug routes, and dormant commands have named owner decisions.
- [ ] Unsafe behavior is excluded from compatibility: state-changing GET, arbitrary message HTML, public PII lookup, direct object references, plaintext credentials, and public diagnostics.
- [ ] Every temporary adapter has telemetry, owner, deadline, removal threshold, and rollback path.
- [ ] Product and engineering owners approve Gate G3.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
- [03 Map target schema and migration rules](./03-map-target-schema-and-migration-rules.md)
