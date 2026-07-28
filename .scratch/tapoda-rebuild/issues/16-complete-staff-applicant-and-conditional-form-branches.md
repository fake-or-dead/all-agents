# Complete staff-applicant and conditional form branches

Status: `ready-for-agent`

## What to build

Complete the remaining initial-application persona matrix without creating new renderers or workflow owners. Support staff applicant intent and all approved course, category, monastic, manager, attendance, travel, facility, representative, emergency, and commitment branches.

## Acceptance criteria

- [ ] Staff applicant intent is stored separately from any later course-staff assignment.
- [ ] Approved trainee/staff, new/alumni, lay/monastic, category, course-type, tutor-type, and D10M contexts resolve deterministically from server-owned facts.
- [ ] Unknown or unsupported category values fail safely and never select a male or privileged default.
- [ ] Manager details, part-time dates and periods, four travel choices, dinner, seating, representative, emergency contact, risk, and property commitments use approved typed or semantic data.
- [ ] Conditional visibility and requiredness match the published version on both client and server; hidden sensitive answers clear as configured.
- [ ] Repeatable groups support stable row identity, add/remove, bounds, and row-level validation.
- [ ] Every approved persona/branch combination passes draft, submit, receipt, accessibility, and responsive fixtures through the shared renderer.

## Blocked by

- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
- [13 Submit an initial new-person application](./13-submit-an-initial-new-person-application.md)
- [14 Publish an immutable application form version](./14-publish-an-immutable-application-form-version.md)
- [15 Submit an alumni application with preserved eligibility](./15-submit-an-alumni-application-with-preserved-eligibility.md)
