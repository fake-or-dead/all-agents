# Approve lifecycle, alumni eligibility, and staff rules

Status: `ready-for-human`

## What to build

Turn the proposed application lifecycle into an approved domain contract. Resolve every ambiguous legacy state, keep review decisions separate from application state, define alumni eligibility provenance, and distinguish staff applicants from assigned course staff.

## Acceptance criteria

- [ ] Every allowed transition, terminal state, actor, prerequisite, reason, and side effect is approved.
- [ ] Applicant-declined invitation remains distinct from reviewer rejection; cancellation, withdrawal, no-show, and completion meanings are explicit.
- [ ] Legacy values including `accepted`, `rejected`, `canceled`, and `left` have evidence-based mapping or quarantine rules.
- [ ] Alumni eligibility defines new completion-based events and legacy-preservation rules with provenance.
- [ ] Staff applicant intent, staff conversion, assigned position, and course-staff permissions are distinct concepts.
- [ ] Status events require previous state, new state, actor, source, reason, time, version, and correlation data.
- [ ] Product and operations owners approve the resulting Gate G2 artifact.

## Blocked by

- [01 Capture production truth baseline](./01-capture-production-truth-baseline.md)
