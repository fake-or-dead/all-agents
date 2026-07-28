import { readFileSync } from 'node:fs';

const data = JSON.parse(readFileSync(new URL('./output-device-fixtures.json', import.meta.url), 'utf8'));
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const equal = (actual, expected, message) => assert(JSON.stringify(actual) === JSON.stringify(expected), `${message}\nactual=${JSON.stringify(actual)}\nexpected=${JSON.stringify(expected)}`);

assert(data.schema_version === '2.0.0', 'unsupported fixture schema');
assert(data.synthetic_only === true, 'fixtures must remain synthetic');

const spec = data.report_spec;
const applications = new Map(data.synthetic_records.applications.map(row => [row.application_id, {
  ...row,
  seniority: data.synthetic_records.application_seniority[row.application_id] ?? Number.MAX_SAFE_INTEGER,
  category: data.synthetic_records.application_categories[row.application_id] ?? 'unknown'
}]));
const teacherRecords = new Map(data.synthetic_records.teacher_records.map(row => [row.teacher_record_id, row]));
const laundryRecords = new Map(data.synthetic_records.laundry_records.map(row => [row.laundry_id, {
  ...row,
  approved_category: data.synthetic_records.laundry_approved_categories[row.laundry_id] ?? 'unknown'
}]));
const fixtures = new Map(data.fixtures.map(fixture => [fixture.id, fixture]));

const normalizeThai = value => value.normalize('NFC').trim().replace(/\s+/gu, ' ');
const collator = new Intl.Collator(spec.sort_rule.locale, spec.sort_rule.options);
const compareThai = (left, right, idKey) => collator.compare(normalizeThai(left.thai_name), normalizeThai(right.thai_name)) || left[idKey].localeCompare(right[idKey], 'en');
const compareApplicant = (left, right) => left.seniority - right.seniority || compareThai(left, right, 'application_id');
const laundryCategoryRank = category => {
  const rank = spec.laundry.approved_categories.indexOf(category);
  return rank < 0 ? Number.MAX_SAFE_INTEGER : rank;
};
const compareLaundry = (left, right) => laundryCategoryRank(left.approved_category) - laundryCategoryRank(right.approved_category)
  || left.room.localeCompare(right.room, 'en')
  || compareThai(left, right, 'laundry_id');
const buddhistDate = value => {
  if (value === null || value === undefined) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  assert(match, `invalid fixture date ${value}`);
  return `${match[3]}/${match[2]}/${Number(match[1]) + spec.date_rule.ce_to_be_offset}`;
};
assert(buddhistDate('2026-07-29') === '29/07/2569', 'Buddhist date conversion drift');

const idsToRows = (ids, source, fixtureId) => ids.map(id => {
  assert(source.has(id), `${fixtureId}: missing input record ${id}`);
  return source.get(id);
});
const groupTemplate = groups => Object.fromEntries(groups.map(group => [group.id, []]));
const counters = grouped => Object.fromEntries(Object.entries(grouped).map(([key, ids]) => [key, ids.length]));

const applicantMembership = rows => {
  const groupedRows = groupTemplate(spec.applicant.groups);
  const audits = [];
  for (const row of rows) {
    if (!spec.applicant.states.include.includes(row.state)) continue;
    const group = spec.applicant.groups.find(candidate => candidate.persona === row.persona && candidate.gender === row.gender);
    if (!group) {
      audits.push(`unknown_persona:${row.application_id}`);
      continue;
    }
    groupedRows[group.id].push(row);
  }
  const grouped = {};
  for (const group of spec.applicant.groups) {
    grouped[group.id] = groupedRows[group.id].sort(compareApplicant).map(row => row.application_id);
  }
  return { grouped, audits };
};

const teacherMembership = rows => {
  const groupedRows = groupTemplate(spec.teacher.groups);
  const audits = [];
  for (const row of rows) {
    if (!spec.teacher.states.includes(row.state)) continue;
    const application = applications.get(row.application_id);
    assert(application, `teacher record ${row.teacher_record_id}: missing application`);
    const derived = spec.teacher.groups.find(group => group.persona === application.persona
      && (group.gender === 'any' || group.gender === application.gender)
      && group.category === application.category);
    if (!derived) {
      audits.push(`classification_unknown:${row.teacher_record_id}`);
      continue;
    }
    if (row.group_id !== derived.id) {
      audits.push(`classification_conflict:${row.teacher_record_id}:supplied=${row.group_id}:derived=${derived.id}`);
      continue;
    }
    groupedRows[derived.id].push(row);
  }
  const grouped = {};
  for (const group of spec.teacher.groups) {
    grouped[group.id] = groupedRows[group.id].sort((a, b) => compareThai(a, b, 'teacher_record_id')).map(row => row.teacher_record_id);
  }
  return { grouped, audits };
};

const capabilitySet = fields => new Set(fields.flatMap(field => Object.values(field.capabilities ?? {})));
const project = (row, fields, surface, capabilities) => {
  const result = {};
  const redacted = [];
  for (const field of fields) {
    if (field.policy === 'always_omit' || field.surface_policy?.[surface] === 'always_omit') continue;
    const needed = field.capabilities?.[surface];
    if (needed && !capabilities.has(needed)) {
      redacted.push(field.key);
      continue;
    }
    result[field.key] = field.format === 'buddhist_date' ? buddhistDate(row[field.key] ?? null) : (row[field.key] ?? null);
  }
  return { result, redacted };
};

for (const fixture of data.fixtures) {
  for (const key of ['spec_version', 'course_session_id', 'requester', 'authorization_result', 'generated_at']) {
    assert(typeof fixture[key] === 'string' && fixture[key].length > 0, `${fixture.id}: missing metadata ${key}`);
  }
  assert(fixture.spec_version === spec.spec_version, `${fixture.id}: spec version drift`);
  assert(!Number.isNaN(Date.parse(fixture.generated_at)), `${fixture.id}: invalid generated_at`);
}

for (const fixtureId of ['zero_rows', 'boundary_states_groups']) {
  const fixture = fixtures.get(fixtureId);
  const appRows = idsToRows(fixture.input.application_ids, applications, fixtureId);
  const appActual = applicantMembership(appRows);
  equal(appActual.grouped, fixture.expected.applicant.screen, `${fixtureId}: applicant screen membership`);
  equal(counters(appActual.grouped), fixture.expected.applicant.counters, `${fixtureId}: applicant counters`);
  equal(appActual.grouped, fixture.expected.applicant.print === 'same_as_screen' ? fixture.expected.applicant.screen : fixture.expected.applicant.print, `${fixtureId}: applicant print membership`);
  equal(appActual.grouped, fixture.expected.applicant.xlsx === 'same_as_screen' ? fixture.expected.applicant.screen : fixture.expected.applicant.xlsx, `${fixtureId}: applicant XLSX membership`);
  equal(appActual.audits, fixture.expected.applicant.audits ?? [], `${fixtureId}: applicant audit disposition`);

  const allApplicantCaps = capabilitySet(spec.applicant.fields);
  for (const groupIds of Object.values(appActual.grouped)) {
    const rows = idsToRows(groupIds, applications, fixtureId);
    const screen = rows.map(row => project(row, spec.applicant.fields, 'screen', allApplicantCaps).result);
    const print = rows.map(row => project(row, spec.applicant.fields, 'print', allApplicantCaps).result);
    const xlsx = rows.map(row => project(row, spec.applicant.fields, 'xlsx', allApplicantCaps).result);
    const expectedPrint = screen.map(({ mental_health, substance_use, ...allowed }) => allowed);
    equal(print, expectedPrint, `${fixtureId}: applicant print omits mental-health/substance-use`);
    equal(screen, xlsx, `${fixtureId}: applicant screen/XLSX field-value parity`);
  }

  const teacherRows = idsToRows(fixture.input.teacher_record_ids, teacherRecords, fixtureId);
  const teacherActual = teacherMembership(teacherRows);
  equal(teacherActual.grouped, fixture.expected.teacher.screen, `${fixtureId}: teacher screen membership`);
  equal(counters(teacherActual.grouped), fixture.expected.teacher.counters, `${fixtureId}: teacher counters`);
  equal(teacherActual.grouped, fixture.expected.teacher.print === 'same_as_screen' ? fixture.expected.teacher.screen : fixture.expected.teacher.print, `${fixtureId}: teacher print membership`);
  equal(teacherActual.audits, fixture.expected.teacher.audits ?? [], `${fixtureId}: teacher classification audits`);
  const allTeacherCaps = capabilitySet(spec.teacher.fields);
  for (const groupIds of Object.values(teacherActual.grouped)) {
    const records = idsToRows(groupIds, teacherRecords, fixtureId).map(row => ({ ...applications.get(row.application_id), ...row }));
    const screen = records.map(row => project(row, spec.teacher.fields, 'screen', allTeacherCaps).result);
    const print = records.map(row => project(row, spec.teacher.fields, 'print', allTeacherCaps).result);
    equal(screen, print, `${fixtureId}: teacher screen/print field-value parity`);
    assert(screen.every(row => !Object.hasOwn(row, 'mental_health') && !Object.hasOwn(row, 'substance_use')), `${fixtureId}: teacher deny fields leaked`);
  }

  const laundryRows = idsToRows(fixture.input.laundry_ids, laundryRecords, fixtureId)
    .filter(row => spec.laundry.states.includes(applications.get(row.application_id)?.state))
    .map(row => ({ ...row, total: row.laundry_cost + row.purchase_cost }))
    .sort(compareLaundry);
  const laundryIds = laundryRows.map(row => row.laundry_id);
  equal(laundryIds, fixture.expected.laundry.screen, `${fixtureId}: laundry screen membership`);
  assert(laundryIds.length === fixture.expected.laundry.counter, `${fixtureId}: laundry counter`);
  equal(laundryIds, fixture.expected.laundry.print, `${fixtureId}: laundry print membership`);
  equal(laundryIds, fixture.expected.laundry.xlsx, `${fixtureId}: laundry XLSX membership`);
  const laundryProjections = laundryRows.map(row => project(row, spec.laundry.fields, 'screen', new Set()).result);
  equal(laundryProjections, laundryRows.map(row => project(row, spec.laundry.fields, 'print', new Set()).result), `${fixtureId}: laundry screen/print field-value parity`);
  equal(laundryProjections, laundryRows.map(row => project(row, spec.laundry.fields, 'xlsx', new Set()).result), `${fixtureId}: laundry screen/XLSX field-value parity`);
  const rowTotals = Object.fromEntries(laundryRows.map(row => [row.laundry_id, row.total]));
  equal(rowTotals, fixture.expected.laundry.row_totals ?? {}, `${fixtureId}: laundry row totals`);
  assert(laundryRows.reduce((sum, row) => sum + row.total, 0) === fixture.expected.laundry.total, `${fixtureId}: laundry grand total`);
}

const sortFixture = fixtures.get('thai_ordering');
const sortRows = idsToRows(sortFixture.input.application_ids, applications, sortFixture.id).sort(compareApplicant);
equal(Object.fromEntries(Object.keys(sortFixture.expected.normalized_names).map(id => [id, normalizeThai(applications.get(id).thai_name)])), sortFixture.expected.normalized_names, 'Thai NFC/space normalization');
equal(sortRows.map(row => row.application_id), sortFixture.expected.new_male_order, 'applicant seniority, Thai collation, immutable ID ordering');
equal(spec.sort_rule.applicant_keys, sortFixture.expected.sort_keys, 'applicant declared sort keys');
assert(normalizeThai(applications.get('app-002').thai_name) === normalizeThai(applications.get('app-014').thai_name), 'tie-break fixture names must normalize equally');

const laundrySortFixture = fixtures.get('laundry_ordering_conflicts');
const laundrySortRows = idsToRows(laundrySortFixture.input.laundry_ids, laundryRecords, laundrySortFixture.id).sort(compareLaundry);
equal(laundrySortRows.map(row => row.laundry_id), laundrySortFixture.expected.order, 'laundry category, room, Thai name, immutable ID ordering');
equal(spec.sort_rule.laundry_keys, laundrySortFixture.expected.sort_keys, 'laundry declared sort keys');

const teacherConflictFixture = fixtures.get('teacher_conflicting_classification');
const teacherConflict = teacherMembership(idsToRows(teacherConflictFixture.input.teacher_record_ids, teacherRecords, teacherConflictFixture.id));
equal(teacherConflict.grouped, teacherConflictFixture.expected.screen, 'teacher group derives from linked application classification');
equal(teacherConflict.audits, teacherConflictFixture.expected.audits, 'teacher conflicting supplied classification rejection');

const goldenFixture = fixtures.get('independent_expected_values');
const goldenApp = applications.get(goldenFixture.input.application_id);
const allApplicantCaps = capabilitySet(spec.applicant.fields);
const goldenMembership = applicantMembership([goldenApp]);
assert(goldenMembership.grouped.alumni_male.length === goldenFixture.expected.applicant.counter.alumni_male, 'independent applicant counter value');
equal(project(goldenApp, spec.applicant.fields, 'screen', allApplicantCaps).result, goldenFixture.expected.applicant.screen, 'independent applicant screen values');
equal(project(goldenApp, spec.applicant.fields, 'print', allApplicantCaps).result, goldenFixture.expected.applicant.print, 'independent applicant print values');
equal(project(goldenApp, spec.applicant.fields, 'xlsx', allApplicantCaps).result, goldenFixture.expected.applicant.xlsx, 'independent applicant XLSX values');
const goldenTeacherRecord = teacherRecords.get(goldenFixture.input.teacher_record_id);
const goldenTeacher = { ...applications.get(goldenTeacherRecord.application_id), ...goldenTeacherRecord };
equal(project(goldenTeacher, spec.teacher.fields, 'print', capabilitySet(spec.teacher.fields)).result, goldenFixture.expected.teacher.print, 'independent teacher print values');
const goldenLaundrySource = laundryRecords.get(goldenFixture.input.laundry_id);
const goldenLaundry = { ...goldenLaundrySource, total: goldenLaundrySource.laundry_cost + goldenLaundrySource.purchase_cost };
const goldenLaundryScreen = project(goldenLaundry, spec.laundry.fields, 'screen', new Set()).result;
equal(goldenLaundryScreen, goldenFixture.expected.laundry.screen, 'independent laundry screen values');
equal(project(goldenLaundry, spec.laundry.fields, 'print', new Set()).result, goldenFixture.expected.laundry.print === 'same_as_screen' ? goldenFixture.expected.laundry.screen : goldenFixture.expected.laundry.print, 'independent laundry print values');
equal(project(goldenLaundry, spec.laundry.fields, 'xlsx', new Set()).result, goldenFixture.expected.laundry.xlsx === 'same_as_screen' ? goldenFixture.expected.laundry.screen : goldenFixture.expected.laundry.xlsx, 'independent laundry XLSX values');

const sensitiveFixture = fixtures.get('sensitive_field_matrix');
const sensitiveRow = applications.get(sensitiveFixture.input.application_id);
for (const fieldCase of sensitiveFixture.expected.fields) {
  const fieldSpec = spec.applicant.fields.find(field => field.key === fieldCase.key);
  assert(fieldSpec, `sensitive field spec missing: ${fieldCase.key}`);
  for (const surface of ['screen', 'print', 'xlsx']) {
    const expected = fieldCase[surface];
    const granted = new Set(expected.capability ? [expected.capability] : []);
    const authorized = project(sensitiveRow, [fieldSpec], surface, granted);
    const denied = project(sensitiveRow, [fieldSpec], surface, new Set());
    if (expected.authorized === 'value') assert(authorized.result[fieldCase.key] === fieldCase.value, `${fieldCase.key}/${surface}: authorized value`);
    else assert(!Object.hasOwn(authorized.result, fieldCase.key), `${fieldCase.key}/${surface}: authorized always omit`);
    if (expected.denied === 'redacted') {
      assert(!Object.hasOwn(denied.result, fieldCase.key), `${fieldCase.key}/${surface}: denied value leaked`);
      equal(denied.redacted, [fieldCase.key], `${fieldCase.key}/${surface}: denied redaction audit`);
    } else {
      assert(!Object.hasOwn(denied.result, fieldCase.key), `${fieldCase.key}/${surface}: denied always omit`);
      equal(denied.redacted, [], `${fieldCase.key}/${surface}: always omit is not capability denial`);
    }
  }
}
const teacherEmergencyCase = sensitiveFixture.expected.teacher_emergency_contact;
const teacherEmergencyRecord = teacherRecords.get(teacherEmergencyCase.teacher_record_id);
const teacherEmergencyRow = { ...applications.get(teacherEmergencyRecord.application_id), ...teacherEmergencyRecord };
const teacherEmergencySpec = spec.teacher.fields.find(field => field.key === 'emergency_contact');
for (const [surface, capability] of [['screen', teacherEmergencyCase.screen_capability], ['print', teacherEmergencyCase.print_capability]]) {
  const authorized = project(teacherEmergencyRow, [teacherEmergencySpec], surface, new Set([capability]));
  const denied = project(teacherEmergencyRow, [teacherEmergencySpec], surface, new Set());
  assert(authorized.result.emergency_contact === teacherEmergencyCase.value, `teacher emergency-contact ${surface}: authorized value`);
  assert(!Object.hasOwn(denied.result, 'emergency_contact'), `teacher emergency-contact ${surface}: denied value leaked`);
  equal(denied.redacted, ['emergency_contact'], `teacher emergency-contact ${surface}: denied redaction`);
}

for (const fixtureId of ['teacher_selection_0', 'teacher_selection_1', 'teacher_selection_10_pagination', 'teacher_selection_11_denied']) {
  const fixture = fixtures.get(fixtureId);
  const selected = idsToRows(fixture.input.teacher_record_ids, teacherRecords, fixtureId);
  const allowed = selected.length <= spec.teacher.max_print_selection;
  const actual = {
    selected: selected.length,
    allowed,
    pages: allowed && selected.length > 0 ? Math.ceil(selected.length / spec.teacher.page_size) : 0,
    ...(fixture.expected.page_size === undefined ? {} : { page_size: spec.teacher.page_size }),
    print: allowed ? selected.map(row => row.teacher_record_id) : []
  };
  equal(actual, fixture.expected, `${fixtureId}: teacher selection/pagination`);
  assert((allowed ? 'allowed' : 'denied_selection_limit') === fixture.authorization_result, `${fixtureId}: authorization result drift`);
}

const authorizeDownload = fixture => {
  const generated = Date.parse(fixture.generated_at);
  const expires = Date.parse(fixture.input.artifact_expires_at);
  if (generated >= expires) return { status: 'expired', rows: [], audit: 'download_expired' };
  const capabilities = new Set(fixture.input.capabilities);
  if (!capabilities.has('report.read') || !capabilities.has('export.request')) return { status: 'denied', rows: [], audit: 'download_denied' };
  const rows = idsToRows(fixture.input.application_ids, applications, fixture.id);
  const projected = rows.map(row => project(row, spec.applicant.fields, fixture.input.surface, capabilities));
  const redactedFields = [...new Set(projected.flatMap(row => row.redacted))];
  return { status: redactedFields.length ? 'allowed_redacted' : 'allowed', row_ids: rows.map(row => row.application_id), redacted_fields: redactedFields, audit: redactedFields.length ? 'download_allowed_redacted' : 'download_allowed', values: projected.map(row => row.result) };
};
for (const fixtureId of ['download_unauthorized', 'download_redacted', 'download_expired']) {
  const fixture = fixtures.get(fixtureId);
  const actual = authorizeDownload(fixture);
  assert(actual.status === fixture.expected.status, `${fixtureId}: status`);
  assert(actual.audit === fixture.expected.audit, `${fixtureId}: audit`);
  equal(actual.row_ids ?? [], fixture.expected.row_ids ?? [], `${fixtureId}: row authorization`);
  equal(actual.redacted_fields ?? [], fixture.expected.redacted_fields ?? [], `${fixtureId}: redaction`);
  if (actual.values) {
    for (const row of actual.values) {
      for (const field of actual.redacted_fields) assert(!Object.hasOwn(row, field), `${fixtureId}: redacted field leaked: ${field}`);
      assert(row.submitted_at === '01/01/2569', `${fixtureId}: Buddhist output date drift`);
    }
  }
  assert(fixture.authorization_result === actual.status, `${fixtureId}: fixture authorization_result drift`);
}

for (const required of ['classification','mental_health','substance_use','history','training','attendance_period','checked_in_at','completed_at','dinner','seating','special_requests']) {
  assert(spec.applicant.fields.some(field => field.key === required), `applicant output field missing: ${required}`);
}

const notifications = data.notifications;
const activeIds = notifications.active_variants.map(variant => variant.id);
equal(activeIds.filter(id => id.startsWith('legacy_confirmation_')), ['legacy_confirmation_d03','legacy_confirmation_d10','legacy_confirmation_staff'], 'three legacy confirmation variants');
equal(activeIds.filter(id => id.startsWith('current_confirmation_')), ['current_confirmation_d03','current_confirmation_d10','current_confirmation_monastic_d10','current_confirmation_staff'], 'four current confirmation variants');
assert(!activeIds.includes('operational'), 'unsupported operational-v1 must not be active');
for (const variant of notifications.active_variants) {
  for (const key of ['recipient','audience','course','template','links','attachment','sender','retry','bounce','failure']) assert(Object.hasOwn(variant, key), `${variant.id}: missing ${key}`);
  assert(variant.sender === notifications.local_sender, `${variant.id}: sender drift`);
  assert(variant.attachment === 'none' || variant.attachment.startsWith('approved-document-key:'), `${variant.id}: attachment disposition`);
  assert(variant.retry === 'local-retry-v1' && variant.bounce === 'terminal-no-state-change', `${variant.id}: delivery semantics`);
}
const email2 = notifications.disabled_variants.find(variant => variant.id === 'email_2');
assert(email2 && email2.enabled === false && email2.status === 'template_unresolved', 'Email 2 disabled disposition');
assert(email2.recipient === null && email2.sender === null && email2.template === null && email2.course === null, 'Email 2 must have no routing/template');
equal(email2.assets, [], 'Email 2 assets');
assert(!notifications.active_variants.includes(email2), 'Email 2 excluded from active inventory');

const thaiId = data.thai_id;
for (const claim of ['iss','kid','jti','nonce','actor_account_id','session_id','action','course_session_id','aud','origin','exp']) assert(thaiId.challenge_claims.includes(claim), `Thai ID claim missing: ${claim}`);
assert(thaiId.consume === 'atomic_jti_and_nonce_before_verification_event', 'Thai ID consume order');
const consumedJti = new Set();
const consumedNonce = new Set();
const rejectThaiId = (reason, correlationId) => ({ decision: 'rejected', reason, audit: { outcome: 'rejected', reason, correlation_id: correlationId } });
const verifyThaiId = (challenge, assertion, correlationId) => {
  const verifier = thaiId.verifier;
  if (challenge.iss !== verifier.trusted_issuer) return rejectThaiId('unknown_issuer', correlationId);
  if (!Object.hasOwn(verifier.trusted_keys, challenge.kid)) return rejectThaiId('unknown_kid', correlationId);
  if (assertion.signature !== verifier.valid_signature) return rejectThaiId('bad_signature', correlationId);
  if (Date.parse(challenge.exp) <= Date.parse(verifier.now)) return rejectThaiId('expired', correlationId);
  if (assertion.aud !== verifier.required_values.aud || assertion.aud !== challenge.aud) return rejectThaiId('audience_mismatch', correlationId);
  if (assertion.action !== verifier.required_values.action || assertion.action !== challenge.action) return rejectThaiId('action_mismatch', correlationId);
  if (assertion.origin !== verifier.required_values.origin || assertion.origin !== challenge.origin) return rejectThaiId('origin_mismatch', correlationId);
  if (assertion.actor_account_id !== challenge.actor_account_id) return rejectThaiId('actor_mismatch', correlationId);
  if (assertion.session_id !== challenge.session_id) return rejectThaiId('session_mismatch', correlationId);
  if (assertion.course_session_id !== challenge.course_session_id) return rejectThaiId('course_session_mismatch', correlationId);
  if (assertion.jti !== challenge.jti) return rejectThaiId('jti_mismatch', correlationId);
  if (assertion.nonce !== challenge.nonce) return rejectThaiId('nonce_mismatch', correlationId);
  if (consumedJti.has(challenge.jti) || consumedNonce.has(challenge.nonce)) return rejectThaiId('already_consumed', correlationId);
  consumedJti.add(challenge.jti);
  consumedNonce.add(challenge.nonce);
  return { decision: 'accepted', reason: 'verified', audit: { outcome: 'accepted', reason: 'verified', correlation_id: correlationId } };
};

for (const testCase of thaiId.cases) {
  const base = {
    iss: thaiId.verifier.trusted_issuer,
    kid: 'kid-local-1',
    jti: `jti-${testCase.id}`,
    nonce: `nonce-${testCase.id}`,
    actor_account_id: 'actor-synthetic-001',
    session_id: 'operator-session-synthetic-001',
    action: thaiId.verifier.required_values.action,
    course_session_id: 'course-session-synthetic-001',
    aud: thaiId.verifier.required_values.aud,
    origin: thaiId.verifier.required_values.origin,
    exp: '2026-07-29T00:05:00Z'
  };
  const challenge = { ...base, ...(testCase.challenge_overrides ?? {}) };
  const assertion = { ...challenge, signature: thaiId.verifier.valid_signature, ...(testCase.assertion_overrides ?? {}) };
  const beforeJti = [...consumedJti];
  const beforeNonce = [...consumedNonce];
  const actual = verifyThaiId(challenge, assertion, `thai-id-${testCase.id}`);
  equal(actual, testCase.expected, `Thai ID verifier case ${testCase.id}`);
  const auditText = JSON.stringify(actual.audit);
  for (const prohibited of ['national_id','thai_name','english_name','date_of_birth','photo','address']) assert(!auditText.includes(prohibited), `${testCase.id}: PII field leaked to audit`);
  if (actual.decision === 'rejected' && actual.reason !== 'already_consumed') {
    equal([...consumedJti], beforeJti, `${testCase.id}: rejected verification consumed jti`);
    equal([...consumedNonce], beforeNonce, `${testCase.id}: rejected verification consumed nonce`);
  }
  if (actual.decision === 'accepted') {
    assert(consumedJti.has(challenge.jti) && consumedNonce.has(challenge.nonce), `${testCase.id}: atomic jti/nonce consume failed`);
  }
}

console.log(`local contract fixtures: valid (${data.fixtures.length} report fixtures, ${notifications.active_variants.length} active notifications, ${thaiId.cases.length} Thai ID cases)`);
