import { readFileSync } from 'node:fs';

const fixture = JSON.parse(readFileSync(new URL('./output-device-fixtures.json', import.meta.url), 'utf8'));
const requireValue = (condition, message) => {
  if (!condition) throw new Error(message);
};

requireValue(fixture.schema_version === '1.0.0', 'unsupported schema version');
requireValue(fixture.synthetic_only === true, 'fixture must remain synthetic');
requireValue(fixture.date_display.example.display === '29/07/2569', 'Buddhist date fixture drift');
requireValue(fixture.report_spec.applicant_groups.length === 8, 'need eight applicant groups');
requireValue(fixture.report_spec.teacher_groups.length === 5, 'need five teacher groups');
requireValue(fixture.report_spec.applicant_state_membership.unknown_persona === 'exclude_from_output_and_emit_audited_unknown_persona', 'unknown persona disposition drift');
requireValue(JSON.stringify(fixture.report_spec.surface_expectations) === JSON.stringify({
  screen: 'same_rows_and_authorized_fields',
  print: 'same_rows_and_authorized_fields',
  xlsx: 'same_rows_and_authorized_fields'
}), 'surface parity drift');

const expectedApplicant = ['alumni_male', 'new_male', 'alumni_female', 'new_female', 'monk', 'nun', 'staff_male', 'staff_female'];
requireValue(JSON.stringify(fixture.report_spec.applicant_groups.map(({ id }) => id)) === JSON.stringify(expectedApplicant), 'applicant group order drift');
const expectedTeacher = ['old_men', 'new_men', 'old_women', 'new_women', 'staff'];
requireValue(JSON.stringify(fixture.report_spec.teacher_groups.map(({ id }) => id)) === JSON.stringify(expectedTeacher), 'teacher group order drift');
requireValue(fixture.report_spec.teacher_state_membership.max_print_selection === 10, 'teacher print cap drift');

const capabilities = fixture.report_spec.redaction_cases.map(({ capability }) => capability);
requireValue(JSON.stringify(capabilities) === JSON.stringify(['medication.read', 'medication.print', 'medication.export', 'national_id.full_read']), 'sensitive capability cases drift');
requireValue(fixture.report_spec.redaction_cases[3].expected === 'deny', 'full national ID must be deny-only');

const { notifications } = fixture;
requireValue(notifications.local_sender === 'tapoda-local-fake@invalid', 'local sender drift');
requireValue(JSON.stringify(notifications.retry_minutes) === JSON.stringify([1, 5, 15]), 'retry schedule drift');
for (const variant of notifications.variants) {
  for (const key of ['id', 'recipient', 'audience', 'course', 'template', 'links', 'attachment', 'sender', 'retry', 'bounce', 'failure']) {
    requireValue(Object.hasOwn(variant, key), `notification ${variant.id} missing ${key}`);
  }
  requireValue(variant.sender === notifications.local_sender, `notification ${variant.id} sender drift`);
  requireValue(variant.retry === 'local-retry-v1' && variant.bounce === 'terminal-no-state-change', `notification ${variant.id} delivery policy drift`);
  requireValue(variant.attachment === 'none' || variant.attachment.startsWith('approved-document-key:'), `notification ${variant.id} attachment disposition drift`);
}
requireValue(notifications.variants.length === 11, 'active notification inventory drift');

const { thai_id: thaiId } = fixture;
for (const claim of ['iss', 'kid', 'jti', 'nonce', 'actor_account_id', 'session_id', 'action', 'course_session_id', 'aud', 'origin', 'exp']) {
  requireValue(thaiId.challenge_claims.includes(claim), `Thai ID claim missing: ${claim}`);
}
requireValue(thaiId.consume === 'atomic_jti_and_nonce_before_verification_event', 'Thai ID consume order drift');
requireValue(thaiId.rejections.includes('already_consumed'), 'Thai ID replay rejection missing');

console.log('local contract fixtures: valid');
