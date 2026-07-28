# Local form and persona contract

**Status:** local implementation decision. **Production-only owner approval is excluded.**

This is the G5 implementation contract. `FormEngine` owns form definition/version, applicability, visibility, validation, and publication. `ApplicationWorkflow` owns drafts, draft answers, immutable submissions, and lifecycle transitions. Forms never transition lifecycle state directly.

## Server-derived context

The browser submits answers only. The server derives and persists this context from the authenticated person, authorized application, course session, eligibility event, and published assignment:

`form_key`, phase, course-session ID, course-type key, tutor-type key, applicant intent (`trainee` or `staff_applicant`), alumni-eligibility key/provenance, lay/monastic category, approved gender/category key, locale, draft ID, and optimistic-lock revision.

Unknown, absent, or unsupported gender/category resolves to `unsupported_persona`: no privileged, male-default, monastic-default, or staff-default schema is returned; the draft remains editable only after a support-authorized correction or explicit course policy resolves the category. The client cannot post context to select a privileged persona.

## Four variants

| Variant | Form key / phase | Ordered stages | Submit condition | Lifecycle command |
|---|---|---|---|---|
| `pre-new` | `initial_application` / initial | profile → preferences → consent | all applicable fields valid; required initial-consent version accepted | freeze initial submission; `draft → submitted` |
| `pre-alumni` | `initial_application` / initial | profile → training history → preferences → consent | same, including applicable training rows | freeze initial submission; `draft → submitted` |
| `post-new` | `post_invitation_confirmation` / confirmation | profile → preferences → teacher details → commitment → management | all applicable fields and confirmation commitments valid | freeze superseding submission; Invitation command separately accepts to `confirmed` |
| `post-alumni` | `post_invitation_confirmation` / confirmation | profile → training history → preferences → teacher details → commitment → management | same, including applicable training rows | freeze superseding submission; Invitation command separately accepts to `confirmed` |

Staff applicant is an intent, not a fifth variant. It selects published staff-applicant applicability within the same four variants. Assigned course staff never derive from form answers.

Profile, training experience/summary, manager detail, attendance plan, facility request, emergency contact, and consent acceptance are typed records/snapshots. They are not generic form-answer blobs.

## Persona and conditional rules

| Context | Server fact | Published applicability / rule |
|---|---|---|
| new/alumni | active eligibility event, including `legacy_preserved` | alumni variants include training-history; no flag supplied by browser |
| trainee/staff applicant | application intent | staff-specific questions/commitments may apply; no course-staff permission |
| lay/monastic | approved category | monastic identity/ordination fields only for monastic context |
| gender/category | approved policy key | category-specific questions only when explicitly assigned by policy; unsupported stops schema resolution |
| course/tutor/D10M | course-session policy keys | published assignment controls teacher details, D10M manager details, and any course-specific field |
| attendance | declared attendance mode | part-time requires dates plus arrival/departure periods; full-time clears part-time-only values |
| travel | `operations.travel_method` | exactly `self`, `center_outbound`, `center_return`, `center_round_trip` |
| dinner/seating | `operations.needs_dinner`, `operations.can_sit_on_floor` | details required only for dinner=`yes` / sit-on-floor=`no` |
| representative/emergency | `declaration.completed_by`; commitment rules | representative fields apply when form completed by representative; emergency contact and commitments remain typed/required by published policy |

The authoritative evaluator runs on every save and submit. The client uses the same declarative rules for feedback only. Supported operators are `equals`, `not_equals`, `in`, `not_in`, `exists`, `all`, `any`, and `not`; no executable code or raw SQL is valid in a form rule.

## Semantic-key registry and legacy mapping

Semantic keys are stable within a form definition. Legacy numeric IDs exist only in `LegacyFormDefinitionAdapter` and `LegacyAnswerAdapter`; mappings retain legacy group/question/choice IDs, raw value, canonical value, evidence, and migration batch.

| Legacy source | Canonical semantic key / handling |
|---|---|
| Q1/Q2 | `health.has_physical_condition`, `health.physical_condition_details` |
| Q15/Q16 | `health.has_recent_substance_use`, `health.substance_use_history` (repeatable typed rows) |
| Q19/Q20 | `health.uses_prescribed_medication`, `health.prescribed_medication_details` |
| Q39/Q40–Q43 | `declaration.completed_by`, `declaration.representative.*` |
| Q46/Q47 | `operations.needs_dinner`, `operations.dinner_reason` |
| Q48/Q49 | `operations.can_sit_on_floor`, `operations.seating_reason` |
| Q57 choices `1,3,4,5` | `operations.travel_method`: `self`, `center_outbound`, `center_return`, `center_round_trip` |
| group 9 | commitment/representative/emergency semantic-key namespace |
| groups 11/12 | preferences semantic-key namespace |
| group 13 | management/travel/facilities semantic-key namespace |
| group 14 | teacher-details semantic-key namespace |
| legacy `nerver`, `alway` | retain raw value; canonicalize to `never`, `always` |
| unmapped legacy group/question/choice | `legacy_form_unmapped` quarantine; no guessed semantic key and no automatic report field |

Each frozen answer records form version, semantic key, typed value, raw migrated value when present, canonical value, question label snapshot, choice label snapshot, visibility result, and answer provenance. Reports resolve submitted snapshots by semantic key, never current question wording or numeric ID.

## Hidden-answer policy

Every published question declares `clear` or `retain`.

- Default: `clear` for hidden dependent sensitive answers, including health/substance/medication details, dinner/seating reasons, representative details, emergency details, and part-time periods.
- `retain` is allowed only for a documented non-sensitive continuity need and remains excluded from validation and ordinary rendering while hidden.
- On every save, the server evaluates visibility, clears `clear` answers atomically, and records the resulting draft revision. Submission freezes only the post-policy answer set.
- A field cannot be required while hidden. Publication rejects this conflict.

## Consent and version rules

- A consent acceptance references immutable `consent_document_version`, person, context, UTC time, evidence, and optional application/submission.
- Registration consent is separate from application consent. `pre-*` requires the assigned initial-application consent; `post-*` requires all assigned confirmation commitments/consents.
- The exact required consent versions are selected by the published form version. A new document version affects future assigned forms only; it cannot invalidate or rewrite historical acceptance.
- Submission atomically freezes the applicable form version, typed snapshots, answers, and consent evidence. A correction starts a new draft and yields a superseding submission.

## Publication and assignment checks

Before a version can publish, Form Studio must pass: unique semantic keys; complete Thai labels/messages; valid question types/choices; restricted rule syntax; existing references; no dependency cycle; no unreachable question; no required-hidden conflict; hidden-answer policy present; no destructive canonical-value change; all four variant/persona fixtures; report mapping or explicit `not_reported`; consent-version assignment; course/session applicability; and accessibility render fixtures.

Published versions are immutable. Draft versions use optimistic locking and cannot receive applicant answers. Local publishing records author, reason, checks, effective assignment, and a local approver identity. **Separate production author/approver approval remains production-only and excluded from this local decision.** Rollback assigns an older published version to future drafts; it never mutates history.

## Local defaults for CLARIFY-001 through CLARIFY-004

| ID | Local default | Implementation rule |
|---|---|---|
| `CLARIFY-001` | Additional fields are a named published section, `application.additional_details`, after `preferences` and before `consent` (`pre-*`) or before `commitment` (`post-*`). | Do not use a popup or controller-only target. Each field needs semantic key, applicability, visibility, validation, and report disposition. Unmapped legacy popup fields quarantine. |
| `CLARIFY-002` | “Dhamma worker” means `staff_applicant` intent only. | It may select published staff questions/communications; it never creates `course_staff`, position, or permissions. Assignment remains a separate authorized command. |
| `CLARIFY-003` | Substance-use answer is structured: `health.has_recent_substance_use` plus zero-or-more `health.substance_use_history` rows. | If the parent is `no`, clear rows. If `yes`, require at least one complete row. Migrate raw `nerver`/`alway` to `never`/`always`, preserve raw input, and do not reproduce stale reload behavior. |
| `CLARIFY-004` | Q082 is not given a guessed semantic key, wording, choice set, group, or migration mapping. | Import affected legacy values into `legacy_form_unmapped`; hide from ordinary forms/reports; expose only to authorized reconciliation. Publish requires a future explicit mapping or approved retirement. |

## References

- [Lifecycle contract](application-lifecycle.md)
- [Product requirements](../../product/tapoda-rebuild-prd.md#92-formengine-detailed-design)
- [Current flow ledger](../../rebuild/current-flow-ledger.md#4-application-and-confirmation-flows)
- [Module blueprint](../../rebuild/module-blueprint.md)
