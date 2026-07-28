import { readFileSync } from 'node:fs';

const data = JSON.parse(readFileSync(new URL('./output-device-fixtures.json', import.meta.url), 'utf8'));
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const equal = (actual, expected, message) => assert(JSON.stringify(actual) === JSON.stringify(expected), `${message}\nactual=${JSON.stringify(actual)}\nexpected=${JSON.stringify(expected)}`);

assert(data.schema_version === '2.0.0', 'unsupported fixture schema');
assert(data.synthetic_only === true, 'fixtures must remain synthetic');

const spec = data.report_spec;
const applications = new Map(data.synthetic_records.applications.map(row => [row.application_id, row]));
const teacherRecords = new Map(data.synthetic_records.teacher_records.map(row => [row.teacher_record_id, row]));
const laundryRecords = new Map(data.synthetic_records.laundry_records.map(row => [row.laundry_id, row]));
const fixtures = new Map(data.fixtures.map(fixture => [fixture.id, fixture]));

const normalizeThai = value => value.normalize('NFC').trim().replace(/\s+/gu, ' ');
const collator = new Intl.Collator(spec.sort_rule.locale, spec.sort_rule.options);
const compareThai = (left, right, idKey) => collator.compare(normalizeThai(left.thai_name), normalizeThai(right.thai_name)) || left[idKey].localeCompare(right[idKey], 'en');
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
    grouped[group.id] = groupedRows[group.id].sort((a, b) => compareThai(a, b, 'application_id')).map(row => row.application_id);
  }
  return { grouped, audits };
};

const teacherMembership = rows => {
  const groupedRows = groupTemplate(spec.teacher.groups);
  for (const row of rows) {
    if (!spec.teacher.states.includes(row.state)) continue;
    assert(groupedRows[row.group_id], `unknown teacher group ${row.group_id}`);
    groupedRows[row.group_id].push(row);
  }
  const grouped = {};
  for (const group of spec.teacher.groups) {
    grouped[group.id] = groupedRows[group.id].sort((a, b) => compareThai(a, b, 'teacher_record_id')).map(row => row.teacher_record_id);
  }
  return grouped;
};

const capabilitySet = fields => new Set(fields.flatMap(field => Object.values(field.capabilities ?? {})));
const project = (row, fields, surface, capabilities) => {
  const result = {};
  const redacted = [];
  for (const field of fields) {
    if (field.policy === 'always_omit') continue;
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
    equal(screen, print, `${fixtureId}: applicant screen/print field-value parity`);
    equal(screen, xlsx, `${fixtureId}: applicant screen/XLSX field-value parity`);
  }

  const teacherRows = idsToRows(fixture.input.teacher_record_ids, teacherRecords, fixtureId);
  const teacherActual = teacherMembership(teacherRows);
  equal(teacherActual, fixture.expected.teacher.screen, `${fixtureId}: teacher screen membership`);
  equal(counters(teacherActual), fixture.expected.teacher.counters, `${fixtureId}: teacher counters`);
  equal(teacherActual, fixture.expected.teacher.print === 'same_as_screen' ? fixture.expected.teacher.screen : fixture.expected.teacher.print, `${fixtureId}: teacher print membership`);
  const allTeacherCaps = capabilitySet(spec.teacher.fields);
  for (const groupIds of Object.values(teacherActual)) {
    const records = idsToRows(groupIds, teacherRecords, fixtureId).map(row => ({ ...applications.get(row.application_id), ...row }));
    const screen = records.map(row => project(row, spec.teacher.fields, 'screen', allTeacherCaps).result);
    const print = records.map(row => project(row, spec.teacher.fields, 'print', allTeacherCaps).result);
    equal(screen, print, `${fixtureId}: teacher screen/print field-value parity`);
    assert(screen.every(row => !Object.hasOwn(row, 'mental_health') && !Object.hasOwn(row, 'substance_use')), `${fixtureId}: teacher deny fields leaked`);
  }

  const laundryRows = idsToRows(fixture.input.laundry_ids, laundryRecords, fixtureId)
    .filter(row => spec.laundry.states.includes(applications.get(row.application_id)?.state))
    .map(row => ({ ...row, total: row.laundry_cost + row.purchase_cost }))
    .sort((a, b) => a.room.localeCompare(b.room, 'en') || compareThai(a, b, 'laundry_id'));
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
const sortRows = idsToRows(sortFixture.input.application_ids, applications, sortFixture.id).sort((a, b) => compareThai(a, b, 'application_id'));
equal(Object.fromEntries(Object.keys(sortFixture.expected.normalized_names).map(id => [id, normalizeThai(applications.get(id).thai_name)])), sortFixture.expected.normalized_names, 'Thai NFC/space normalization');
equal(sortRows.map(row => row.application_id), sortFixture.expected.new_male_order, 'Thai collation and immutable ID tie-break');
assert(normalizeThai(applications.get('app-002').thai_name) === normalizeThai(applications.get('app-014').thai_name), 'tie-break fixture names must normalize equally');

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

for (const claim of ['iss','kid','jti','nonce','actor_account_id','session_id','action','course_session_id','aud','origin','exp']) assert(data.thai_id.challenge_claims.includes(claim), `Thai ID claim missing: ${claim}`);
assert(data.thai_id.consume === 'atomic_jti_and_nonce_before_verification_event', 'Thai ID consume order');
assert(data.thai_id.rejections.includes('already_consumed'), 'Thai ID replay rejection');

console.log(`local contract fixtures: valid (${data.fixtures.length} fixtures, ${notifications.active_variants.length} active notifications)`);
