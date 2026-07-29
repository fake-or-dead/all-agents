<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MemberBrowserFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! app()->environment(['local', 'testing'])
            || config('identity-access.verification_adapter') !== 'deterministic-fake'
        ) {
            throw new RuntimeException('Member browser fixtures require local deterministic-fake mode.');
        }

        DB::table('provinces')->upsert([[
            'id' => 'member-bkk',
            'code' => 'MB10',
            'name_th' => 'กรุงเทพมหานคร (ทดสอบ)',
            'name_en' => 'Bangkok test fixture',
            'active' => true,
            'display_order' => 900,
        ]], ['id'], ['name_th', 'name_en', 'active', 'display_order']);
        DB::table('amphoes')->upsert([[
            'id' => 'member-phra',
            'province_id' => 'member-bkk',
            'code' => 'MB1001',
            'name_th' => 'พระนคร (ทดสอบ)',
            'name_en' => 'Phra Nakhon test fixture',
            'active' => true,
            'display_order' => 900,
        ]], ['id'], ['province_id', 'name_th', 'name_en', 'active', 'display_order']);
        DB::table('tambons')->upsert([[
            'id' => 'member-wat',
            'amphoe_id' => 'member-phra',
            'code' => 'MB101',
            'name_th' => 'พระบรมมหาราชวัง (ทดสอบ)',
            'name_en' => 'Wat test fixture',
            'postcode' => '10200',
            'active' => true,
            'display_order' => 900,
        ]], ['id'], ['amphoe_id', 'name_th', 'name_en', 'postcode', 'active', 'display_order']);
        DB::table('application_workflow_facts')->upsert([[
            'course_session_id' => '10000000-0000-4000-8000-000000000001',
            'person_id' => '20000000-0000-4000-8000-000000000001',
            'state' => 'draft',
            'created_at' => CarbonImmutable::parse('2026-07-29T09:00:00+07:00'),
            'updated_at' => CarbonImmutable::parse('2026-07-29T09:15:00+07:00'),
        ]], ['course_session_id', 'person_id'], ['state', 'updated_at']);
    }
}
