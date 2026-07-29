<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CourseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Synthetic course fixtures may only be seeded locally or in tests.');
        }

        DB::transaction(function (): void {
            DB::table('provinces')->upsert([
                ['id' => 'bkk', 'code' => '10', 'name_th' => 'กรุงเทพมหานคร', 'name_en' => 'Bangkok', 'active' => true, 'display_order' => 1],
                ['id' => 'cbi', 'code' => '20', 'name_th' => 'ชลบุรี', 'name_en' => 'Chon Buri', 'active' => true, 'display_order' => 2],
                ['id' => 'empty', 'code' => '99', 'name_th' => 'จังหวัดตัวอย่าง', 'name_en' => 'Fixture Province', 'active' => true, 'display_order' => 99],
            ], ['id'], ['code', 'name_th', 'name_en', 'active', 'display_order']);

            DB::table('amphoes')->upsert([
                ['id' => 'bkk-phra-nakhon', 'province_id' => 'bkk', 'code' => '1001', 'name_th' => 'พระนคร', 'name_en' => 'Phra Nakhon', 'active' => true, 'display_order' => 1],
                ['id' => 'cbi-sattahip', 'province_id' => 'cbi', 'code' => '2009', 'name_th' => 'สัตหีบ', 'name_en' => 'Sattahip', 'active' => true, 'display_order' => 1],
            ], ['id'], ['province_id', 'code', 'name_th', 'name_en', 'active', 'display_order']);

            DB::table('tambons')->upsert([
                ['id' => 'bkk-bowon', 'amphoe_id' => 'bkk-phra-nakhon', 'code' => '100102', 'name_th' => 'บวรนิเวศ', 'name_en' => 'Bowon Niwet', 'postcode' => '10200', 'active' => true, 'display_order' => 1],
                ['id' => 'cbi-na-chom', 'amphoe_id' => 'cbi-sattahip', 'code' => '200902', 'name_th' => 'นาจอมเทียน', 'name_en' => 'Na Chom Thian', 'postcode' => '20250', 'active' => true, 'display_order' => 1],
            ], ['id'], ['amphoe_id', 'code', 'name_th', 'name_en', 'postcode', 'active', 'display_order']);

            DB::table('course_types')->upsert([
                ['id' => 'meditation', 'name_th' => 'ปฏิบัติธรรม', 'name_en' => 'Meditation', 'active' => true],
                ['id' => 'service', 'name_th' => 'อบรมจิตอาสา', 'name_en' => 'Service', 'active' => true],
            ], ['id'], ['name_th', 'name_en', 'active']);

            DB::table('centers')->upsert([
                [
                    'id' => 'tapoda',
                    'name_th' => 'ศูนย์ตโปทาราม',
                    'name_en' => 'Tapoda Center',
                    'address_th' => 'อำเภอสัตหีบ จังหวัดชลบุรี',
                    'province_id' => 'cbi',
                    'map_url' => 'https://maps.example.invalid/tapoda',
                    'active' => true,
                ],
                [
                    'id' => 'bangkok',
                    'name_th' => 'ศูนย์กรุงเทพฯ',
                    'name_en' => 'Bangkok Center',
                    'address_th' => 'เขตพระนคร กรุงเทพมหานคร',
                    'province_id' => 'bkk',
                    'map_url' => 'https://maps.example.invalid/bangkok',
                    'active' => true,
                ],
            ], ['id'], ['name_th', 'name_en', 'address_th', 'province_id', 'map_url', 'active']);

            DB::table('courses')->upsert([
                [
                    'id' => 'd10',
                    'course_type_id' => 'meditation',
                    'title_th' => 'หลักสูตรปฏิบัติธรรม 10 วัน',
                    'summary_th' => 'ฝึกสติและสมาธิอย่างต่อเนื่องในสภาพแวดล้อมที่เหมาะสม',
                ],
                [
                    'id' => 'volunteer',
                    'course_type_id' => 'service',
                    'title_th' => 'หลักสูตรเตรียมจิตอาสา',
                    'summary_th' => 'เตรียมความพร้อมสำหรับผู้สมัครเจ้าหน้าที่หลักสูตร',
                ],
            ], ['id'], ['course_type_id', 'title_th', 'summary_th']);

            DB::table('course_sessions')->upsert([
                [
                    'id' => '10000000-0000-4000-8000-000000000001',
                    'code' => 'D10-2026-08-TAPODA',
                    'course_id' => 'd10',
                    'center_id' => 'tapoda',
                    'starts_on' => '2026-08-10',
                    'ends_on' => '2026-08-20',
                    'registration_opens_at' => '2026-07-01 00:00:00+07',
                    'registration_closes_at' => '2026-08-05 23:59:59+07',
                    'policy_version' => '2026-08-v1',
                    'timezone' => 'Asia/Bangkok',
                    'minimum_age' => 20,
                    'maximum_age' => 70,
                    'applicant_type' => 'trainee',
                    'approved_categories' => json_encode(['female', 'male', 'monastic'], JSON_THROW_ON_ERROR),
                    'invite_only' => false,
                    'published' => true,
                ],
                [
                    'id' => '10000000-0000-4000-8000-000000000002',
                    'code' => 'STAFF-2026-09-BKK',
                    'course_id' => 'volunteer',
                    'center_id' => 'bangkok',
                    'starts_on' => '2026-09-05',
                    'ends_on' => '2026-09-06',
                    'registration_opens_at' => '2026-07-01 00:00:00+07',
                    'registration_closes_at' => '2026-08-20 23:59:59+07',
                    'policy_version' => '2026-09-v1',
                    'timezone' => 'Asia/Bangkok',
                    'minimum_age' => 25,
                    'maximum_age' => 65,
                    'applicant_type' => 'staff',
                    'approved_categories' => json_encode(['female', 'male'], JSON_THROW_ON_ERROR),
                    'invite_only' => true,
                    'published' => true,
                ],
                [
                    'id' => '10000000-0000-4000-8000-000000000003',
                    'code' => 'D10-2026-06-TAPODA',
                    'course_id' => 'd10',
                    'center_id' => 'tapoda',
                    'starts_on' => '2026-06-01',
                    'ends_on' => '2026-06-10',
                    'registration_opens_at' => '2026-04-01 00:00:00+07',
                    'registration_closes_at' => '2026-05-25 23:59:59+07',
                    'policy_version' => '2026-06-v2',
                    'timezone' => 'Asia/Bangkok',
                    'minimum_age' => 40,
                    'maximum_age' => 75,
                    'applicant_type' => 'trainee',
                    'approved_categories' => json_encode(['female', 'male'], JSON_THROW_ON_ERROR),
                    'invite_only' => false,
                    'published' => true,
                ],
            ], ['id'], ['code', 'course_id', 'center_id', 'starts_on', 'ends_on', 'registration_opens_at', 'registration_closes_at', 'policy_version', 'timezone', 'minimum_age', 'maximum_age', 'applicant_type', 'approved_categories', 'invite_only', 'published']);

            DB::table('teachers')->upsert([
                ['id' => 'teacher-suda', 'name_th' => 'อาจารย์สุดา', 'active' => true],
                ['id' => 'teacher-wichai', 'name_th' => 'อาจารย์วิชัย', 'active' => true],
            ], ['id'], ['name_th', 'active']);

            DB::table('course_session_teachers')->upsert([
                ['course_session_id' => '10000000-0000-4000-8000-000000000001', 'teacher_id' => 'teacher-suda', 'display_order' => 1],
                ['course_session_id' => '10000000-0000-4000-8000-000000000001', 'teacher_id' => 'teacher-wichai', 'display_order' => 2],
            ], ['course_session_id', 'teacher_id'], ['display_order']);

            DB::table('course_capacity_rules')->upsert([
                ['course_session_id' => '10000000-0000-4000-8000-000000000001', 'category' => 'female', 'capacity' => 30, 'reserved_count' => 28],
                ['course_session_id' => '10000000-0000-4000-8000-000000000001', 'category' => 'male', 'capacity' => 25, 'reserved_count' => 25],
                ['course_session_id' => '10000000-0000-4000-8000-000000000001', 'category' => 'monastic', 'capacity' => 5, 'reserved_count' => 1],
                ['course_session_id' => '10000000-0000-4000-8000-000000000002', 'category' => 'female', 'capacity' => 10, 'reserved_count' => 4],
                ['course_session_id' => '10000000-0000-4000-8000-000000000002', 'category' => 'male', 'capacity' => 10, 'reserved_count' => 4],
                ['course_session_id' => '10000000-0000-4000-8000-000000000003', 'category' => 'female', 'capacity' => 30, 'reserved_count' => 30],
                ['course_session_id' => '10000000-0000-4000-8000-000000000003', 'category' => 'male', 'capacity' => 25, 'reserved_count' => 25],
            ], ['course_session_id', 'category'], ['capacity', 'reserved_count']);

            DB::table('document_publication_projections')->upsert([
                [
                    'course_session_id' => '10000000-0000-4000-8000-000000000001',
                    'key' => 'training-intro',
                    'title_th' => 'คู่มือเตรียมตัวเข้าร่วมหลักสูตร',
                    'version' => 1,
                    'checksum' => hash('sha256', 'local-training-intro-v1'),
                    'visibility' => 'public',
                    'approval_state' => 'approved',
                    'lifecycle_state' => 'active',
                    'quarantine_reason' => null,
                    'disposition' => 'local-placeholder',
                ],
            ], ['course_session_id', 'key'], ['title_th', 'version', 'checksum', 'visibility', 'approval_state', 'lifecycle_state', 'quarantine_reason', 'disposition']);
        });
    }
}
