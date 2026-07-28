# Freeze form, persona, consent, and semantic-key mappings

Status: `ready-for-human`

## What to build

Create the approved form contract for all four guided variants and every conditional persona. Map legacy groups, questions, choices, stored values, typed profile data, training data, and consent evidence to immutable form versions and stable semantic keys.

## Acceptance criteria

- [x] `pre-new`, `pre-alumni`, `post-new`, and `post-alumni` stages and completion rules are approved locally.
- [x] Trainee, staff applicant, alumni, lay, monastic, category, course type, tutor type, D10M, travel, attendance, dinner, seating, representative, emergency, and commitment branches are mapped locally.
- [x] Every relevant legacy group, question, choice, misspelled value, and conditional relation maps to a semantic key, quarantine, or locally approved retirement rule.
- [x] Unknown gender/category never falls through to a privileged or male-default question set.
- [x] Profile, training, manager, attendance, and consent remain typed records where required.
- [x] Form publication checks, report mappings, consent versions, local author/approver roles, and historical snapshot rules are approved locally.
- [x] `CLARIFY-001` through `CLARIFY-004` have local defaults; Gate G5 production sign-off remains open.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)

## Local completion

2026-07-29 — Local implementation contract completed: [form contract](../../../docs/decisions/local/form-contract.md). Production-only owner approval and Gate G5 sign-off are explicitly excluded.
