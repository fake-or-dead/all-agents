<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalApplicationConsentSeeder extends Seeder
{
    public const string DocumentId = '30000000-0000-4000-8000-000000000001';

    public const string VersionId = '30000000-0000-4000-8000-000000000002';

    public const string Content = 'เอกสารยินยอมการสมัครหลักสูตรสำหรับทดสอบภายในเท่านั้น ใช้ยืนยันการอ้างอิงเวอร์ชันและ checksum ใน Form Engine ไม่ใช่ข้อความกฎหมายสำหรับระบบจริง';

    public function run(): void
    {
        if (! app(Application::class)->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Synthetic application-consent fixtures may only be seeded locally or in tests.',
            );
        }

        $checksum = hash('sha256', self::Content);
        $now = CarbonImmutable::parse('2026-07-29T00:00:00Z');
        $existing = DB::table('consent_document_versions')->where('id', self::VersionId)->first();

        if ($existing !== null) {
            $document = DB::table('consent_documents')
                ->where('id', self::DocumentId)
                ->first();

            if (
                $document === null
                || $document->document_key !== 'application-consent'
                || $document->title !== 'ความยินยอมการสมัครหลักสูตร (ตัวอย่างภายใน)'
                || $document->purpose !== 'course_application'
                || $existing->content_checksum !== $checksum
                || $existing->document_id !== self::DocumentId
                || $existing->content !== self::Content
                || $existing->version_label !== 'local-application-v1'
                || $existing->locale !== 'th'
                || $existing->status !== 'published'
                || ! $this->timestampMatches($document->created_at, $now)
                || ! $this->timestampMatches($document->updated_at, $now)
                || ! $this->timestampMatches($existing->published_at, $now)
                || ! $this->timestampMatches($existing->created_at, $now)
                || ! $this->timestampMatches($existing->updated_at, $now)
            ) {
                throw new RuntimeException('Existing application-consent fixture does not match.');
            }

            return;
        }

        DB::transaction(function () use ($checksum, $now): void {
            DB::table('consent_documents')->insert([
                'id' => self::DocumentId,
                'document_key' => 'application-consent',
                'title' => 'ความยินยอมการสมัครหลักสูตร (ตัวอย่างภายใน)',
                'purpose' => 'course_application',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('consent_document_versions')->insert([
                'id' => self::VersionId,
                'document_id' => self::DocumentId,
                'version_label' => 'local-application-v1',
                'locale' => 'th',
                'content' => self::Content,
                'content_checksum' => $checksum,
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    private function timestampMatches(mixed $actual, CarbonImmutable $expected): bool
    {
        return is_string($actual)
            && CarbonImmutable::parse($actual, 'UTC')->equalTo($expected);
    }
}
