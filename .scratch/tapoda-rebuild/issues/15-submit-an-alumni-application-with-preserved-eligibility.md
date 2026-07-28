# Submit an alumni application with preserved eligibility

Status: `ready-for-agent`

## What to build

Extend the initial application journey for an alumni person. Resolve eligibility from auditable events, include normalized or provenance-preserving training history, submit an immutable `pre-alumni` revision, and prevent legacy alumni from silently falling into the new-person flow.

## Acceptance criteria

- [ ] Alumni flow selection uses an active eligibility event with reason, source, rule version, and provenance.
- [ ] Legacy eligibility generated from approved historical states remains available until an approved recalculation policy changes it.
- [ ] Training experiences support row-level records while unexpandable legacy totals remain explicit summaries rather than invented events.
- [ ] The ordered `pre-alumni` stages include profile, training history, preferences, and consent.
- [ ] Submission freezes the exact training evidence and current profile used for this course session.
- [ ] Editing current training or profile data later cannot alter the receipt, reviewed submission, or report representation.
- [ ] New, legacy, revoked, contradictory, and missing eligibility scenarios plus `FLOW-APP-03` pass.

## Blocked by

- [02 Approve lifecycle, alumni eligibility, and staff rules](./02-approve-lifecycle-alumni-and-staff-rules.md)
- [06 Freeze form, persona, consent, and semantic-key mappings](./06-freeze-form-persona-consent-and-semantic-key-mappings.md)
- [12 Manage profile, training history, applications, and security](./12-manage-profile-training-history-applications-and-security.md)
- [13 Submit an initial new-person application](./13-submit-an-initial-new-person-application.md)
- [14 Publish an immutable application form version](./14-publish-an-immutable-application-form-version.md)
