# Freeze form, persona, consent, and semantic-key mappings

Status: `ready-for-human`

## What to build

Create the approved form contract for all four guided variants and every conditional persona. Map legacy groups, questions, choices, stored values, typed profile data, training data, and consent evidence to immutable form versions and stable semantic keys.

## Acceptance criteria

- [ ] `pre-new`, `pre-alumni`, `post-new`, and `post-alumni` stages and completion rules are approved.
- [ ] Trainee, staff applicant, alumni, lay, monastic, category, course type, tutor type, D10M, travel, attendance, dinner, seating, representative, emergency, and commitment branches are mapped.
- [ ] Every relevant legacy group, question, choice, misspelled value, and conditional relation maps to a semantic key or approved retirement.
- [ ] Unknown gender/category never falls through to a privileged or male-default question set.
- [ ] Profile, training, manager, attendance, and consent remain typed records where required.
- [ ] Form publication checks, report mappings, consent versions, author/approver roles, and historical snapshot rules are approved.
- [ ] `CLARIFY-001` through `CLARIFY-004` are resolved and Gate G5 is signed.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
