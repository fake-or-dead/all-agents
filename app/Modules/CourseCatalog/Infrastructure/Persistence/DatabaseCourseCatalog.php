<?php

namespace App\Modules\CourseCatalog\Infrastructure\Persistence;

use App\Modules\ApplicationWorkflow\Contracts\ApplicationFacts;
use App\Modules\CourseCatalog\Contracts\CourseCatalog;
use App\Modules\CourseCatalog\Data\CourseSearch;
use App\Modules\CourseCatalog\Data\CourseSearchResult;
use App\Modules\CourseCatalog\Data\CourseSessionView;
use App\Modules\CourseCatalog\Data\EligibilityContext;
use App\Modules\CourseCatalog\Data\EligibilityResult;
use App\Modules\CourseCatalog\Data\HttpsUrl;
use App\Modules\DocumentsConsent\Contracts\PublicCourseDocuments;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseCourseCatalog implements CourseCatalog
{
    public function __construct(
        private ApplicationFacts $applicationFacts,
        private PublicCourseDocuments $documents,
    ) {}

    public function search(CourseSearch $search): CourseSearchResult
    {
        $query = $this->baseSessionQuery()
            ->where('course_sessions.published', true)
            ->orderBy('course_sessions.starts_on')
            ->orderBy('course_sessions.code');

        if ($search->errors !== []) {
            $query->whereRaw('1 = 0');
        } else {
            if ($search->year !== null) {
                $query->whereYear('course_sessions.starts_on', $search->year);
            }

            if ($search->month !== null) {
                $query->whereMonth('course_sessions.starts_on', $search->month);
            }

            if ($search->courseType !== null) {
                $query->where('course_types.id', $search->courseType);
            }

            if ($search->center !== null) {
                $query->where('centers.id', $search->center);
            }
        }

        $sessions = $query->get()->map(fn (object $row): array => [
            'code' => (string) $row->code,
            'title' => (string) $row->title_th,
            'course_type' => (string) $row->course_type_name,
            'center' => (string) $row->center_name,
            'starts_on' => (string) $row->starts_on,
            'ends_on' => (string) $row->ends_on,
            'registration_status' => $this->registrationStatus($row),
            'invite_only' => (bool) $row->invite_only,
        ])->all();

        return new CourseSearchResult(
            $sessions,
            $this->options('course_types'),
            $this->options('centers'),
        );
    }

    public function session(string $code, EligibilityContext $context): ?CourseSessionView
    {
        $row = $this->baseSessionQuery()
            ->where('course_sessions.published', true)
            ->where('course_sessions.code', $code)
            ->first();

        if ($row === null) {
            return null;
        }

        $teachers = DB::table('course_session_teachers')
            ->join('teachers', 'teachers.id', '=', 'course_session_teachers.teacher_id')
            ->where('course_session_teachers.course_session_id', $row->id)
            ->where('teachers.active', true)
            ->orderBy('course_session_teachers.display_order')
            ->pluck('teachers.name_th')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $capacityRules = DB::table('course_capacity_rules')
            ->where('course_session_id', $row->id)
            ->orderBy('category')
            ->get()
            ->map(fn (object $rule): array => [
                'category' => (string) $rule->category,
                'capacity' => (int) $rule->capacity,
                'reserved_count' => (int) $rule->reserved_count,
                'remaining' => (int) $rule->capacity - (int) $rule->reserved_count,
            ])->all();

        $documents = array_map(static fn ($document): array => [
            'key' => $document->key,
            'title' => $document->title,
            'url' => route('public.document-placeholder', ['documentKey' => $document->key], false),
            'version' => $document->version,
            'checksum' => $document->checksum,
            'disposition' => $document->disposition,
        ], $this->documents->forSession((string) $row->id));

        $approvedCategories = json_decode((string) $row->approved_categories, true);
        $categorySourceValid = json_last_error() === JSON_ERROR_NONE
            && is_array($approvedCategories)
            && array_is_list($approvedCategories)
            && collect($approvedCategories)->every(
                static fn (mixed $category): bool => is_string($category),
            );
        $categories = $categorySourceValid ? $approvedCategories : [];
        $mapUrl = HttpsUrl::fromUntrusted((string) $row->map_url);
        $sourceErrors = $this->sourceErrors(
            $row,
            $categories,
            $categorySourceValid,
            $capacityRules,
        );

        $session = [
            'id' => (string) $row->id,
            'code' => (string) $row->code,
            'title' => (string) $row->title_th,
            'summary' => (string) $row->summary_th,
            'course_type' => (string) $row->course_type_name,
            'center' => [
                'name' => (string) $row->center_name,
                'address' => (string) $row->address_th,
                'map_url' => $mapUrl?->value,
            ],
            'starts_on' => (string) $row->starts_on,
            'ends_on' => (string) $row->ends_on,
            'registration_opens_at' => (string) $row->registration_opens_at,
            'registration_closes_at' => (string) $row->registration_closes_at,
            'registration_status' => $this->registrationStatus($row),
            'invite_only' => (bool) $row->invite_only,
            'policy_version' => (string) $row->policy_version,
            'timezone' => (string) $row->timezone,
            'minimum_age' => $row->minimum_age === null ? null : (int) $row->minimum_age,
            'maximum_age' => $row->maximum_age === null ? null : (int) $row->maximum_age,
            'applicant_type' => (string) $row->applicant_type,
            'approved_categories' => $categories,
            'teachers' => $teachers,
            'capacity_rules' => $capacityRules,
            'documents' => $documents,
            'source_errors' => $sourceErrors,
        ];

        return new CourseSessionView(
            $session,
            $this->eligibility($session, $capacityRules, $context),
        );
    }

    private function baseSessionQuery(): Builder
    {
        return DB::table('course_sessions')
            ->join('courses', 'courses.id', '=', 'course_sessions.course_id')
            ->join('course_types', 'course_types.id', '=', 'courses.course_type_id')
            ->join('centers', 'centers.id', '=', 'course_sessions.center_id')
            ->select([
                'course_sessions.*',
                'courses.title_th',
                'courses.summary_th',
                'course_types.name_th as course_type_name',
                'centers.name_th as center_name',
                'centers.address_th',
                'centers.map_url',
            ]);
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function options(string $table): array
    {
        return DB::table($table)
            ->where('active', true)
            ->orderBy('name_th')
            ->get(['id', 'name_th'])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'label' => (string) $row->name_th,
            ])->all();
    }

    /**
     * @param  object|array<string, mixed>  $session
     */
    private function registrationStatus(object|array $session): string
    {
        try {
            $timezone = new DateTimeZone((string) data_get($session, 'timezone'));
            $opensAt = CarbonImmutable::parse(data_get($session, 'registration_opens_at'));
            $closesAt = CarbonImmutable::parse(data_get($session, 'registration_closes_at'));
            $endsAt = CarbonImmutable::parse(
                (string) data_get($session, 'ends_on'),
                $timezone,
            )->endOfDay();
        } catch (\Throwable) {
            return 'invalid';
        }
        $now = CarbonImmutable::now()->setTimezone($timezone);

        if ($now->isAfter($endsAt)) {
            return 'closed';
        }

        if ($now->isBefore($opensAt)) {
            return 'upcoming';
        }

        if ($now->isAfter($closesAt)) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  list<array<string, mixed>>  $capacityRules
     */
    private function eligibility(
        array $session,
        array $capacityRules,
        EligibilityContext $context,
    ): EligibilityResult {
        if ($session['source_errors'] !== []) {
            return new EligibilityResult(
                'unavailable',
                'invalid-source-state',
                'ข้อมูลนโยบายรอบหลักสูตรไม่สมบูรณ์ ระบบระงับการประเมินไว้',
                $session['source_errors'],
            );
        }

        if ($this->sessionHasEnded($session)) {
            return new EligibilityResult(
                'unavailable',
                'session-ended',
                'รอบหลักสูตรนี้สิ้นสุดแล้ว',
            );
        }

        if ($session['invite_only'] === true) {
            return new EligibilityResult(
                'unavailable',
                'invite-only',
                'หลักสูตรนี้รับเฉพาะผู้ที่ได้รับคำเชิญ',
            );
        }

        if ($session['registration_status'] !== 'open') {
            return new EligibilityResult(
                'unavailable',
                "registration-{$session['registration_status']}",
                $session['registration_status'] === 'upcoming'
                    ? 'ยังไม่เปิดรับสมัคร'
                    : 'ปิดรับสมัครแล้ว',
            );
        }

        $missing = [];
        foreach (['age', 'category', 'applicantType'] as $field) {
            if ($context->{$field} === null) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return new EligibilityResult(
                'unknown',
                'input-required',
                'กรอกข้อมูลให้ครบเพื่อประเมินสิทธิ์เบื้องต้น',
                $missing,
            );
        }

        $applicationState = $this->applicationFacts->state(
            (string) $session['id'],
            $context->actorId,
        );

        if (! in_array($applicationState, ['none', 'not-checked'], true)) {
            return new EligibilityResult(
                'unavailable',
                'existing-application',
                'มีใบสมัครสำหรับรอบนี้อยู่แล้ว กรุณาเข้าสู่ระบบเพื่อตรวจสอบสถานะ',
                [$applicationState],
            );
        }

        if ($context->applicantType !== $session['applicant_type']) {
            return new EligibilityResult(
                'unavailable',
                'applicant-type',
                'ประเภทผู้สมัครไม่ตรงกับนโยบายของหลักสูตร',
            );
        }

        if (! in_array($context->category, $session['approved_categories'], true)) {
            return new EligibilityResult(
                'unavailable',
                'category',
                'ประเภทผู้สมัครไม่อยู่ในกลุ่มที่หลักสูตรรับสมัคร',
            );
        }

        if (
            ($session['minimum_age'] !== null && $context->age < $session['minimum_age'])
            || ($session['maximum_age'] !== null && $context->age > $session['maximum_age'])
        ) {
            return new EligibilityResult(
                'unavailable',
                'age',
                'อายุไม่อยู่ในช่วงที่หลักสูตรกำหนด',
            );
        }

        $capacity = collect($capacityRules)->firstWhere('category', $context->category);
        if ($capacity === null || $capacity['remaining'] < 1) {
            return new EligibilityResult(
                'unavailable',
                'capacity',
                'ที่นั่งสำหรับประเภทผู้สมัครนี้เต็มแล้ว',
            );
        }

        return new EligibilityResult(
            'eligible',
            $applicationState === 'not-checked' ? 'preliminary-eligible' : 'eligible',
            $applicationState === 'not-checked'
                ? 'ผ่านเกณฑ์เบื้องต้น กรุณาเข้าสู่ระบบเพื่อตรวจสอบใบสมัครเดิมก่อนสมัคร'
                : 'ผ่านเกณฑ์การสมัครหลักสูตรนี้',
        );
    }

    /**
     * @param  list<string>  $categories
     * @param  list<array<string, mixed>>  $capacityRules
     * @return list<string>
     */
    private function sourceErrors(
        object $row,
        array $categories,
        bool $categorySourceValid,
        array $capacityRules,
    ): array {
        $errors = [];
        $allowedCategories = ['female', 'male', 'monastic'];
        $allowedApplicantTypes = ['trainee', 'staff'];

        try {
            $timezone = new DateTimeZone((string) $row->timezone);
            $startsOn = CarbonImmutable::parse((string) $row->starts_on, $timezone)->startOfDay();
            $endsOn = CarbonImmutable::parse((string) $row->ends_on, $timezone)->endOfDay();
            $opensAt = CarbonImmutable::parse((string) $row->registration_opens_at)->setTimezone($timezone);
            $closesAt = CarbonImmutable::parse((string) $row->registration_closes_at)->setTimezone($timezone);

            if ($startsOn->isAfter($endsOn)) {
                $errors[] = 'invalid-session-date-range';
            }
            if ($opensAt->isAfter($closesAt)) {
                $errors[] = 'invalid-registration-window';
            }
            if ($opensAt->isAfter($startsOn) || $closesAt->isAfter($startsOn)) {
                $errors[] = 'registration-after-session-start';
            }
        } catch (\Throwable) {
            $errors[] = 'invalid-date';
        }

        if (! in_array((string) $row->timezone, DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'invalid-timezone';
        }

        $minimumAge = $row->minimum_age === null ? null : (int) $row->minimum_age;
        $maximumAge = $row->maximum_age === null ? null : (int) $row->maximum_age;
        if (
            ($minimumAge !== null && ($minimumAge < 1 || $minimumAge > 120))
            || ($maximumAge !== null && ($maximumAge < 1 || $maximumAge > 120))
            || ($minimumAge !== null && $maximumAge !== null && $minimumAge > $maximumAge)
        ) {
            $errors[] = 'invalid-age-range';
        }

        if (! in_array((string) $row->applicant_type, $allowedApplicantTypes, true)) {
            $errors[] = 'invalid-applicant-type';
        }

        if (
            ! $categorySourceValid
            || $categories === []
            || count($categories) !== count(array_unique($categories))
            || array_diff($categories, $allowedCategories) !== []
        ) {
            $errors[] = 'invalid-categories';
        }

        $capacityCategories = [];
        foreach ($capacityRules as $rule) {
            $capacityCategories[] = $rule['category'];
            if (
                ! in_array($rule['category'], $categories, true)
                || $rule['capacity'] < 1
                || $rule['reserved_count'] < 0
                || $rule['reserved_count'] > $rule['capacity']
            ) {
                $errors[] = 'invalid-capacity';
                break;
            }
        }
        if (array_diff($categories, $capacityCategories) !== []) {
            $errors[] = 'missing-capacity';
        }

        if (trim((string) $row->policy_version) === '') {
            $errors[] = 'missing-policy-version';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionHasEnded(array $session): bool
    {
        try {
            $timezone = new DateTimeZone((string) $session['timezone']);
            $endsAt = CarbonImmutable::parse((string) $session['ends_on'], $timezone)->endOfDay();

            return CarbonImmutable::now()->setTimezone($timezone)->isAfter($endsAt);
        } catch (\Throwable) {
            return true;
        }
    }
}
