<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('กรอกข้อมูลให้ครบเพื่อประเมินสิทธิ์เบื้องต้น');
    }

    public function test_eligibility_returns_explicit_eligible_and_unavailable_outcomes(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        $this->get('/course/detail/D10-2026-08-TAPODA?age=30&category=female&applicant_type=trainee')
            ->assertOk()
            ->assertSee('preliminary-eligible');

        $this->get('/course/detail/D10-2026-08-TAPODA?age=30&category=male&applicant_type=trainee')
            ->assertOk()
            ->assertSee('capacity')
            ->assertSee('ที่นั่งสำหรับประเภทผู้สมัครนี้เต็มแล้ว');

        $this->get('/course/detail/D10-2026-08-TAPODA?age=18&category=female&applicant_type=trainee')
            ->assertOk()
            ->assertSee('age')
            ->assertSee('อายุไม่อยู่ในช่วงที่หลักสูตรกำหนด');

        $this->get('/course/detail/D10-2026-08-TAPODA?age=30&category=female&applicant_type=staff')
            ->assertOk()
            ->assertSee('applicant-type')
            ->assertSee('ประเภทผู้สมัครไม่ตรงกับนโยบายของหลักสูตร');

        DB::table('courses')->where('id', 'd10')->update([
            'approved_categories' => json_encode(['female', 'male'], JSON_THROW_ON_ERROR),
        ]);

        $this->get('/course/detail/D10-2026-08-TAPODA?age=30&category=monastic&applicant_type=trainee')
            ->assertOk()
            ->assertSee('category')
            ->assertSee('ประเภทผู้สมัครไม่อยู่ในกลุ่มที่หลักสูตรรับสมัคร');

        $this->get('/course/detail/STAFF-2026-09-BKK?age=30&category=female&applicant_type=staff')
            ->assertOk()
            ->assertSee('invite-only');

        $this->get('/course/detail/D10-2026-06-TAPODA?age=30&category=female&applicant_type=trainee')
            ->assertOk()
            ->assertSee('registration-closed')
            ->assertSee('ปิดรับสมัครแล้ว');
    }

    public function test_existing_application_state_is_actor_scoped_and_blocks_duplicate_application(): void
    {
        $this->travelTo('2026-07-29 12:00:00');

        DB::table('course_application_facts')->insert([
            'course_session_id' => '10000000-0000-4000-8000-000000000001',
            'actor_id' => 'existing-applicant',
            'state' => 'submitted',
        ]);

        $this
            ->withHeader('X-Tapoda-Test-Actor', 'existing-applicant')
            ->get('/course/detail/D10-2026-08-TAPODA?age=30&category=female&applicant_type=trainee')
            ->assertOk()
            ->assertSee('existing-application')
            ->assertSee('มีใบสมัครสำหรับรอบนี้อยู่แล้ว');
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
}
