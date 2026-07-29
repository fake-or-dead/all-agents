<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RequireActiveAccountSession;
use App\Models\Account;
use App\Modules\IdentityAccess\Contracts\ApplicantIdentityResolver;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PublicCourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);
    }

    public function test_catalog_is_server_rendered_with_shareable_get_filters(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        $this->get('/course?year=2026&month=8&course_type=meditation&center=tapoda')
            ->assertOk()
            ->assertSee('<html lang="th">', false)
            ->assertSee('ค้นหาหลักสูตร')
            ->assertSee('name="year"', false)
            ->assertSee('name="month"', false)
            ->assertSee('name="course_type"', false)
            ->assertSee('name="center"', false)
            ->assertSee('D10-2026-08-TAPODA')
            ->assertSee('ปี พ.ศ.')
            ->assertSee('<option value="2026" selected>2569</option>', false)
            ->assertSee('<option value="8" selected>สิงหาคม</option>', false)
            ->assertSee('10 สิงหาคม 2569')
            ->assertSee('20 สิงหาคม 2569')
            ->assertSee('datetime="2026-08-10"', false)
            ->assertDontSee('STAFF-2026-09-BKK')
            ->assertDontSee('data-page=', false);
    }

    public function test_catalog_reports_empty_and_malformed_filter_states_without_guessing(): void
    {
        $this->get('/course?year=2099')
            ->assertOk()
            ->assertSee('ไม่พบหลักสูตรตามตัวกรอง');

        $this->get('/course?month=not-a-month')
            ->assertOk()
            ->assertSee('ตรวจสอบตัวกรอง')
            ->assertSee('ค่าตัวกรองไม่ถูกต้อง');
    }

    public function test_course_detail_exposes_policy_availability_documents_and_map(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        $this->get('/course/detail/D10-2026-08-TAPODA')
            ->assertOk()
            ->assertSee('หลักสูตรปฏิบัติธรรม 10 วัน')
            ->assertSee('อาจารย์สุดา')
            ->assertSee('เหลือ 2 จาก 30 ที่นั่ง')
            ->assertSee('คู่มือเตรียมตัวเข้าร่วมหลักสูตร')
            ->assertSee('https://maps.example.invalid/tapoda', false)
            ->assertSee('10 สิงหาคม 2569')
            ->assertSee('20 สิงหาคม 2569')
            ->assertSee('1 กรกฎาคม 2569 เวลา 00:00 น. (Asia/Bangkok)')
            ->assertSee('5 สิงหาคม 2569 เวลา 23:59 น. (Asia/Bangkok)')
            ->assertSee('datetime="2026-07-01 00:00:00+07"', false)
            ->assertSee('กรอกข้อมูลให้ครบเพื่อประเมินสิทธิ์เบื้องต้น');
    }

    public function test_eligibility_returns_explicit_eligible_and_unavailable_outcomes(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('preliminary-eligible');

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', [...$this->eligibleInput(), 'category' => 'male'])
            ->assertOk()
            ->assertSee('capacity')
            ->assertSee('ที่นั่งสำหรับประเภทผู้สมัครนี้เต็มแล้ว');

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', [...$this->eligibleInput(), 'age' => '18'])
            ->assertOk()
            ->assertSee('age')
            ->assertSee('อายุไม่อยู่ในช่วงที่หลักสูตรกำหนด');

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', [...$this->eligibleInput(), 'applicant_type' => 'staff'])
            ->assertOk()
            ->assertSee('applicant-type')
            ->assertSee('ประเภทผู้สมัครไม่ตรงกับนโยบายของหลักสูตร');

        DB::table('course_sessions')->where('code', 'D10-2026-08-TAPODA')->update([
            'approved_categories' => json_encode(['female', 'male'], JSON_THROW_ON_ERROR),
        ]);
        DB::table('course_capacity_rules')
            ->where('course_session_id', '10000000-0000-4000-8000-000000000001')
            ->where('category', 'monastic')
            ->delete();

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', [...$this->eligibleInput(), 'category' => 'monastic'])
            ->assertOk()
            ->assertSee('category')
            ->assertSee('ประเภทผู้สมัครไม่อยู่ในกลุ่มที่หลักสูตรรับสมัคร');

        $this->post('/course/detail/STAFF-2026-09-BKK/eligibility', [...$this->eligibleInput(), 'applicant_type' => 'staff'])
            ->assertOk()
            ->assertSee('invite-only');

        $this->post('/course/detail/D10-2026-06-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('session-ended')
            ->assertSee('รอบหลักสูตรนี้สิ้นสุดแล้ว');
    }

    public function test_existing_application_state_uses_authenticated_person_ownership(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        $owner = $this->registerAccount(
            'owner-course@example.test',
            'OWNER123',
        );
        $ownerAccountId = (string) $owner->getAuthIdentifier();
        $ownerPersonId = (string) $owner->getAttribute('person_id');
        DB::table('application_workflow_facts')->insert([
            'course_session_id' => '10000000-0000-4000-8000-000000000001',
            'person_id' => $ownerPersonId,
            'state' => 'submitted',
        ]);

        $this
            ->actingAs($owner)
            ->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('existing-application')
            ->assertSee('มีใบสมัครสำหรับรอบนี้อยู่แล้ว');

        $other = $this->registerAccount(
            'other-course@example.test',
            'OTHER456',
        );
        $this
            ->actingAs($other)
            ->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('<code>eligible</code>', false)
            ->assertDontSee('existing-application');

        $this->post('/signout')->assertRedirect('/signin');
        $this->app['auth']->forgetGuards();
        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('preliminary-eligible');

        $administrativePrincipal = new NonApplicantPrincipal;
        $administrativePrincipal->forceFill(['id' => '44444444-4444-4444-8444-444444444444']);
        $administrativeRequest = Request::create('/course', 'GET');
        $administrativeRequest->setUserResolver(
            static fn (): NonApplicantPrincipal => $administrativePrincipal,
        );
        self::assertNull(
            $this->app
                ->make(ApplicantIdentityResolver::class)
                ->resolve($administrativeRequest),
        );

        $staleAuthenticatedOwner = Account::query()->findOrFail($ownerAccountId);
        $staleOwnerRequest = Request::create('/course', 'GET');
        $staleOwnerRequest->setUserResolver(
            static fn (): Account => $staleAuthenticatedOwner,
        );
        $identityResolver = $this->app->make(ApplicantIdentityResolver::class);
        self::assertSame(
            $ownerPersonId,
            $identityResolver->resolve($staleOwnerRequest)?->personId,
        );
        DB::table('accounts')->where('id', $ownerAccountId)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
        self::assertNull($identityResolver->resolve($staleOwnerRequest));

        // The account-session middleware independently revokes inactive sessions.
        // Bypass only that outer guard to prove Course Catalog itself fails closed
        // if a stale framework principal reaches the production resolver.
        $this->withoutMiddleware(RequireActiveAccountSession::class);
        $this
            ->actingAs($staleAuthenticatedOwner)
            ->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('preliminary-eligible')
            ->assertDontSee('existing-application');

        DB::table('accounts')->where('id', $ownerAccountId)->update([
            'status' => 'active',
            'updated_at' => now(),
        ]);
        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('existing-application');
    }

    public function test_eligibility_post_keeps_sensitive_inputs_out_of_url_and_private_caches(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        $response = $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');

        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertNull($response->headers->get('Location'));
    }

    public function test_untrusted_catalog_content_and_links_remain_inert(): void
    {
        DB::table('courses')->where('id', 'd10')->update([
            'title_th' => '</title><script>alert(1)</script>',
            'summary_th' => '" onmouseover="alert(1)',
        ]);
        DB::table('centers')->where('id', 'tapoda')->update([
            'map_url' => 'javascript:alert(1)',
        ]);

        $response = $this->get('/course/detail/D10-2026-08-TAPODA')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDontSee('</title><script>', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee('ไม่มีลิงก์แผนที่ที่ตรวจสอบแล้ว');

        self::assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));

        DB::table('centers')->where('id', 'tapoda')->update(['map_url' => '//evil.example/map']);
        $this->get('/course/detail/D10-2026-08-TAPODA')
            ->assertOk()
            ->assertDontSee('href="//evil.example', false);
    }

    public function test_documents_port_exposes_only_approved_public_owned_projection(): void
    {
        $rows = [];
        foreach ([
            ['private-doc', 'เอกสารส่วนตัว', 'private', 'approved', 'active', hash('sha256', 'private'), null, '10000000-0000-4000-8000-000000000001'],
            ['quarantined-doc', 'เอกสารกักกัน', 'public', 'approved', 'active', hash('sha256', 'quarantine'), 'malware', '10000000-0000-4000-8000-000000000001'],
            ['missing-checksum', 'เอกสารไร้ checksum', 'public', 'approved', 'active', null, null, '10000000-0000-4000-8000-000000000001'],
            ['retired-doc', 'เอกสารเลิกใช้', 'public', 'approved', 'retired', hash('sha256', 'retired'), null, '10000000-0000-4000-8000-000000000001'],
            ['foreign-doc', 'เอกสารต่างรอบ', 'public', 'approved', 'active', hash('sha256', 'foreign'), null, '10000000-0000-4000-8000-000000000002'],
        ] as [$key, $title, $visibility, $approval, $lifecycle, $checksum, $quarantine, $sessionId]) {
            $rows[] = [
                'course_session_id' => $sessionId,
                'key' => $key,
                'title_th' => $title,
                'version' => 1,
                'checksum' => $checksum,
                'visibility' => $visibility,
                'approval_state' => $approval,
                'lifecycle_state' => $lifecycle,
                'quarantine_reason' => $quarantine,
                'disposition' => 'local-placeholder',
            ];
        }
        DB::table('document_publication_projections')->insert($rows);

        $this->get('/course/detail/D10-2026-08-TAPODA')
            ->assertOk()
            ->assertSee('คู่มือเตรียมตัวเข้าร่วมหลักสูตร')
            ->assertDontSee('เอกสารส่วนตัว')
            ->assertDontSee('เอกสารกักกัน')
            ->assertDontSee('เอกสารไร้ checksum')
            ->assertDontSee('เอกสารเลิกใช้')
            ->assertDontSee('เอกสารต่างรอบ');

        foreach (['private-doc', 'quarantined-doc', 'missing-checksum', 'retired-doc', 'foreign-doc'] as $key) {
            $this->get("/documents/{$key}")->assertNotFound();
        }
    }

    public function test_session_policy_is_versioned_per_session_and_invalid_source_is_quarantined(): void
    {
        $this->travelTo('2026-07-29 12:00:00');
        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertSee('preliminary-eligible');

        $this->travelTo('2026-05-01 12:00:00');
        $this->post('/course/detail/D10-2026-06-TAPODA/eligibility', $this->eligibleInput())
            ->assertSee('age');

        DB::table('course_sessions')->where('code', 'D10-2026-08-TAPODA')->update([
            'minimum_age' => 80,
            'maximum_age' => 20,
        ]);
        DB::table('course_capacity_rules')
            ->where('course_session_id', '10000000-0000-4000-8000-000000000001')
            ->where('category', 'female')
            ->update(['reserved_count' => 31]);

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('invalid-source-state')
            ->assertSee('ข้อมูลจำนวนที่นั่งไม่ถูกต้อง')
            ->assertDontSee('เหลือ 0 จาก 30');
    }

    public function test_registration_window_respects_exact_timezone_boundary(): void
    {
        $this->travelTo('2026-08-05 16:59:59 UTC');
        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertSee('preliminary-eligible');

        $this->travelTo('2026-08-05 17:00:00 UTC');
        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertSee('registration-closed');
    }

    public function test_unknown_policy_values_and_inverted_window_are_rejected(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        DB::table('course_sessions')->where('code', 'D10-2026-08-TAPODA')->update([
            'applicant_type' => 'superuser',
            'approved_categories' => json_encode(['alien'], JSON_THROW_ON_ERROR),
            'registration_opens_at' => '2026-08-06 00:00:00+07',
            'registration_closes_at' => '2026-08-05 00:00:00+07',
        ]);

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('invalid-source-state');
    }

    public function test_raw_category_shape_and_cross_session_windows_fail_closed(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        foreach ([
            json_encode(['unexpected' => 'female'], JSON_THROW_ON_ERROR),
            json_encode(['female', ['unexpected' => true]], JSON_THROW_ON_ERROR),
        ] as $malformedCategories) {
            DB::table('course_sessions')->where('code', 'D10-2026-08-TAPODA')->update([
                'approved_categories' => $malformedCategories,
            ]);

            $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
                ->assertOk()
                ->assertSee('invalid-source-state')
                ->assertDontSee('preliminary-eligible');
        }

        DB::table('course_sessions')->where('code', 'D10-2026-08-TAPODA')->update([
            'approved_categories' => json_encode(['female', 'male', 'monastic'], JSON_THROW_ON_ERROR),
            'registration_opens_at' => '2026-08-21 00:00:00+07',
            'registration_closes_at' => '2026-08-22 00:00:00+07',
        ]);

        $this->post('/course/detail/D10-2026-08-TAPODA/eligibility', $this->eligibleInput())
            ->assertOk()
            ->assertSee('invalid-source-state')
            ->assertDontSee('preliminary-eligible');
    }

    public function test_ended_session_never_returns_eligible(): void
    {
        $this->travelTo('2026-06-11 00:00:00 Asia/Bangkok');

        $this->post('/course/detail/D10-2026-06-TAPODA/eligibility', [
            'age' => '45',
            'category' => 'female',
            'applicant_type' => 'trainee',
        ])
            ->assertOk()
            ->assertSee('session-ended')
            ->assertDontSee('preliminary-eligible');
    }

    public function test_public_content_and_document_compatibility_urls_have_safe_outcomes(): void
    {
        foreach (['/suggest', '/applicant-qualifications', '/about'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<html lang="th">', false);
        }

        $response = $this->get('/documents/training-intro')
            ->assertNotFound()
            ->assertSee('ยังไม่มีเอกสารในระบบท้องถิ่น');

        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    /**
     * @return array{age: string, category: string, applicant_type: string}
     */
    private function eligibleInput(): array
    {
        return ['age' => '30', 'category' => 'female', 'applicant_type' => 'trainee'];
    }

    private function registerAccount(string $email, string $passport): Account
    {
        $this->postJson('/auth/verification/request', ['email' => $email])
            ->assertAccepted();
        $registrationToken = $this->postJson('/auth/verification/verify', [
            'email' => $email,
            'code' => '246810',
        ])->assertOk()->json('registration_token');

        $this->postJson('/signup', [
            'email' => $email,
            'registration_token' => $registrationToken,
            'identity_type' => 'passport',
            'identity_number' => $passport,
            'given_name' => 'ผู้สมัคร',
            'family_name' => 'ทดสอบ',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'consent_accepted' => true,
            'consent_version' => '10000000-0000-4000-8000-000000000002',
        ])->assertCreated();

        return Account::query()->findOrFail((string) auth()->id());
    }
}

final class NonApplicantPrincipal extends Authenticatable {}
