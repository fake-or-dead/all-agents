<?php

namespace Tests\Feature\Member;

use App\Models\Account;
use App\Modules\People\Contracts\MemberProfileMutations;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use App\Modules\People\Data\TrainingUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

final class MemberCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_shell_requires_an_active_owned_person_and_masks_identifier(): void
    {
        [$accountId, $personId] = $this->createAccount('1234567890123');

        $this->get('/member/profile')->assertRedirect('/signin');

        $this->asAccount($accountId)
            ->get('/member/profile')
            ->assertOk()
            ->assertHeaderMissing('X-Person-Identifier')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Member/Center', false)
                ->where('activeTab', 'profile')
                ->where('profile.personId', $personId)
                ->where('profile.identifier.masked', '•••••••••0123')
                ->missing('profile.identifier.value')
                ->where('applications', [])
                ->where('profile.version', 1));

        DB::table('accounts')->where('id', $accountId)->update(['status' => 'disabled']);

        $this->asAccount($accountId)
            ->get('/member/profile')
            ->assertRedirect('/signin');
    }

    public function test_profile_update_is_owned_validated_audited_and_optimistically_locked(): void
    {
        [$accountId, $personId] = $this->createAccount('2234567890123');

        $this->asAccount($accountId)
            ->putJson('/member/profile', [
                'given_name' => 'สมชาย',
                'family_name' => 'ทดสอบ',
                'email' => 'member@example.test',
                'phone' => '1',
                'version' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'รูปแบบ โทรศัพท์ ไม่ถูกต้อง');
        $this->assertDatabaseHas('people', [
            'id' => $personId,
            'profile_version' => 1,
        ]);

        $this->asAccount($accountId)
            ->putJson('/member/profile', [
                'given_name' => 'สมชาย',
                'family_name' => 'ทดสอบ',
                'email' => 'member@example.test',
                'phone' => '0812345678',
                'version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('profile.version', 2);

        $this->assertDatabaseHas('people', [
            'id' => $personId,
            'given_name' => 'สมชาย',
            'family_name' => 'ทดสอบ',
            'profile_version' => 2,
        ]);
        $contact = DB::table('person_contacts')->where('person_id', $personId)->first();
        self::assertNotNull($contact);
        self::assertSame('member@example.test', decrypt($contact->email_encrypted));
        self::assertSame('0812345678', decrypt($contact->phone_encrypted));
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $accountId,
            'action' => 'people.profile.updated',
            'resource_id' => $personId,
            'outcome' => 'succeeded',
        ]);

        $this->asAccount($accountId)
            ->putJson('/member/profile', [
                'given_name' => 'ข้อมูลเก่า',
                'family_name' => 'ห้ามทับ',
                'email' => 'stale@example.test',
                'phone' => '0899999999',
                'version' => 1,
            ])
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'ข้อมูลถูกแก้ไขจากอุปกรณ์อื่น กรุณาโหลดใหม่',
                'code' => 'stale',
            ]);

        $this->assertDatabaseMissing('people', ['given_name' => 'ข้อมูลเก่า']);
    }

    public function test_address_requires_active_canonical_thai_parent_child_chain_and_retains_input(): void
    {
        [$accountId, $personId] = $this->createAccount('3234567890123');
        $this->seedThaiReferences();

        $payload = [
            'address_line_1' => '99 ถนนทดสอบ',
            'address_line_2' => 'ห้อง 8',
            'province_id' => 'p-bkk',
            'amphoe_id' => 'a-phra',
            'tambon_id' => 't-wat',
            'version' => 0,
        ];
        $this->asAccount($accountId)
            ->putJson('/member/address', $payload)
            ->assertOk()
            ->assertJsonPath('address.postcode', '10200')
            ->assertJsonPath('address.version', 1);

        $this->assertDatabaseHas('person_addresses', [
            'person_id' => $personId,
            'province_id' => 'p-bkk',
            'amphoe_id' => 'a-phra',
            'tambon_id' => 't-wat',
            'postcode' => '10200',
            'version' => 1,
        ]);

        $invalid = array_merge($payload, [
            'address_line_1' => 'ค่าที่ผู้ใช้กรอกต้องคงอยู่',
            'amphoe_id' => 'a-other',
            'version' => 1,
        ]);
        $this->asAccount($accountId)
            ->putJson('/member/address', $invalid)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid-reference')
            ->assertJsonPath('input.address_line_1', 'ค่าที่ผู้ใช้กรอกต้องคงอยู่')
            ->assertJsonPath('errors.amphoe_id.0', 'อำเภอไม่อยู่ในจังหวัดที่เลือก');

        $this->assertDatabaseMissing('person_addresses', [
            'person_id' => $personId,
            'address_line_1_encrypted' => encrypt('ค่าที่ผู้ใช้กรอกต้องคงอยู่'),
        ]);
    }

    public function test_training_history_has_deterministic_order_and_stale_write_protection(): void
    {
        [$accountId] = $this->createAccount('4234567890123');
        [$otherAccountId] = $this->createAccount('4334567890123');

        $older = $this->asAccount($accountId)
            ->withHeader('Idempotency-Key', 'training-history-older')
            ->postJson('/member/training', [
                'course_name' => 'หลักสูตรเก่า',
                'provider_name' => 'ศูนย์หนึ่ง',
                'started_on' => '2024-01-10',
                'ended_on' => '2024-01-12',
            ])->assertCreated()->json('training');
        $newer = $this->asAccount($accountId)
            ->withHeader('Idempotency-Key', 'training-history-newer')
            ->postJson('/member/training', [
                'course_name' => 'หลักสูตรใหม่',
                'provider_name' => 'ศูนย์สอง',
                'started_on' => '2025-02-10',
                'ended_on' => '2025-02-12',
            ])->assertCreated()->json('training');

        $this->asAccount($accountId)
            ->get('/member/training')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Member/Center', false)
                ->where('training.0.id', $newer['id'])
                ->where('training.1.id', $older['id']));

        $this->asAccount($accountId)
            ->withHeader('Idempotency-Key', 'training-history-invalid-date')
            ->postJson('/member/training', [
                'course_name' => 'วันไม่ถูกต้อง',
                'provider_name' => 'ศูนย์หนึ่ง',
                'started_on' => '2025-04-10',
                'ended_on' => '2025-04-09',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ended_on.0', 'วันที่จบ ต้องไม่ก่อนวันที่เริ่ม');

        $updated = $this->asAccount($accountId)
            ->putJson("/member/training/{$older['id']}", [
                'course_name' => 'หลักสูตรแก้ไข',
                'provider_name' => 'ศูนย์หนึ่งแก้ไข',
                'started_on' => '2024-01-11',
                'ended_on' => '2024-01-13',
                'version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('training.version', 2)
            ->json('training');
        self::assertSame($older['id'], $updated['id']);

        $this->asAccount($otherAccountId)
            ->putJson("/member/training/{$older['id']}", [
                'course_name' => 'ห้ามแก้ของผู้อื่น',
                'provider_name' => 'ศูนย์อื่น',
                'started_on' => '2024-01-11',
                'ended_on' => '2024-01-13',
                'version' => 2,
            ])
            ->assertNotFound()
            ->assertJsonPath('code', 'denied');
        $stored = DB::table('person_training_experiences')->where('id', $older['id'])->first();
        self::assertNotNull($stored);
        self::assertSame('หลักสูตรแก้ไข', decrypt($stored->course_name_encrypted));
        self::assertSame(2, (int) $stored->version);

        $this->asAccount($accountId)
            ->putJson("/member/training/{$older['id']}", [
                'course_name' => 'ข้อมูลเก่าห้ามทับ',
                'provider_name' => 'ศูนย์หนึ่ง',
                'started_on' => '2024-01-10',
                'ended_on' => '2024-01-12',
                'version' => 1,
            ])->assertStatus(409)->assertJsonPath('code', 'stale');
    }

    public function test_training_creation_idempotency_replays_conflicts_and_scopes_keys_to_the_actor_person(): void
    {
        [$accountId, $personId] = $this->createAccount('4434567890123');
        [$otherAccountId, $otherPersonId] = $this->createAccount('4534567890123');
        $key = 'training-member-idempotency-20260729';
        $payload = [
            'course_name' => 'หลักสูตรส่งซ้ำ',
            'provider_name' => 'ศูนย์ทดสอบ',
            'started_on' => '2025-06-10',
            'ended_on' => '2025-06-12',
        ];

        $this->asAccount($accountId)
            ->postJson('/member/training', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.idempotency_key.0',
                'คีย์คำขอต้องมี 8–128 ตัวอักษรที่ระบบรองรับ',
            );

        $created = $this->asAccount($accountId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/member/training', $payload)
            ->assertCreated()
            ->json('training');
        $firstClaim = DB::table('person_training_idempotency')
            ->where('account_id', $accountId)
            ->where('person_id', $personId)
            ->where('idempotency_key', $key)
            ->first();
        self::assertNotNull($firstClaim);
        self::assertObjectHasProperty('payload_encrypted', $firstClaim);
        self::assertObjectNotHasProperty('payload_digest', $firstClaim);
        $firstCiphertext = (string) $firstClaim->payload_encrypted;
        $serializedClaim = json_encode($firstClaim, JSON_THROW_ON_ERROR);
        foreach (array_values($payload) as $plaintextValue) {
            self::assertStringNotContainsString($plaintextValue, $serializedClaim);
        }
        $replayed = $this->asAccount($accountId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/member/training', $payload)
            ->assertOk()
            ->assertJsonPath('code', 'idempotent-replay')
            ->json('training');
        self::assertSame($created, $replayed);
        $this->assertDatabaseCount('person_training_experiences', 1);
        self::assertSame(
            1,
            DB::table('audit_events')
                ->where('actor_id', $accountId)
                ->where('action', 'people.training.added')
                ->where('outcome', 'succeeded')
                ->count(),
        );

        $this->asAccount($accountId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/member/training', [
                ...$payload,
                'course_name' => 'ห้ามใช้คีย์เดิมกับข้อมูลใหม่',
            ])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'คีย์คำขอนี้ถูกใช้กับข้อมูลอื่นแล้ว',
                'code' => 'idempotency-conflict',
            ]);
        $this->assertDatabaseCount('person_training_experiences', 1);

        $other = $this->asAccount($otherAccountId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/member/training', $payload)
            ->assertCreated()
            ->json('training');
        self::assertNotSame($created['id'], $other['id']);
        $this->assertDatabaseHas('person_training_experiences', [
            'id' => $other['id'],
            'person_id' => $otherPersonId,
        ]);

        $distinct = $this->asAccount($accountId)
            ->withHeader('Idempotency-Key', 'training-member-idempotency-distinct')
            ->postJson('/member/training', $payload)
            ->assertCreated()
            ->json('training');
        $secondCiphertext = DB::table('person_training_idempotency')
            ->where('account_id', $accountId)
            ->where('person_id', $personId)
            ->where('idempotency_key', 'training-member-idempotency-distinct')
            ->value('payload_encrypted');
        self::assertIsString($secondCiphertext);
        self::assertNotSame($firstCiphertext, $secondCiphertext);
        $encrypter = $this->app->make(Encrypter::class);
        $firstPayload = $encrypter->decrypt($firstCiphertext, false);
        $secondPayload = $encrypter->decrypt($secondCiphertext, false);
        self::assertIsString($firstPayload);
        self::assertIsString($secondPayload);
        self::assertSame(
            $firstPayload,
            $secondPayload,
        );
        self::assertNotSame($created['id'], $distinct['id']);
        self::assertSame(
            2,
            DB::table('person_training_experiences')
                ->where('person_id', $personId)
                ->count(),
        );
    }

    #[Group('service-integration')]
    public function test_real_postgresql_concurrent_training_retries_create_one_training_and_audit(): void
    {
        if (
            getenv('REQUIRE_REAL_SERVICES') !== '1'
            || DB::connection()->getDriverName() !== 'pgsql'
            || ! function_exists('pcntl_fork')
            || ! function_exists('posix_kill')
        ) {
            self::markTestSkipped('Real PostgreSQL plus pcntl/posix is required.');
        }

        [$accountId, $personId] = $this->createAccount('4634567890123');
        $idempotencyKey = 'training-concurrent-retry-20260729';
        $resultFile = tempnam(sys_get_temp_dir(), 'tapoda-training-');
        self::assertNotFalse($resultFile);
        DB::connection()->commit();
        $children = [];

        try {
            foreach (range(1, 2) as $attempt) {
                $pid = pcntl_fork();
                self::assertNotSame(-1, $pid);

                if ($pid === 0) {
                    DB::purge();
                    DB::reconnect();
                    $result = $this->app->make(MemberProfileMutations::class)->addTraining(
                        $accountId,
                        new TrainingUpdate(
                            $personId,
                            null,
                            'หลักสูตรพร้อมกัน',
                            'ศูนย์พร้อมกัน',
                            CarbonImmutable::parse('2025-07-10'),
                            CarbonImmutable::parse('2025-07-12'),
                            null,
                        ),
                        $idempotencyKey,
                    );
                    file_put_contents(
                        $resultFile,
                        "{$result->code}:".($result->value['id'] ?? 'none')."\n",
                        FILE_APPEND | LOCK_EX,
                    );
                    posix_kill(posix_getpid(), SIGKILL);
                }

                $children[] = $pid;
            }

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertTrue(pcntl_wifsignaled($status));
            }

            DB::purge();
            DB::reconnect();
            $results = file($resultFile, FILE_IGNORE_NEW_LINES);
            self::assertIsArray($results);
            self::assertCount(2, $results);
            self::assertSame(
                ['idempotent-replay', 'ok'],
                collect($results)
                    ->map(static fn (string $result): string => explode(':', $result, 2)[0])
                    ->sort()
                    ->values()
                    ->all(),
            );
            self::assertCount(
                1,
                collect($results)
                    ->map(static fn (string $result): string => explode(':', $result, 2)[1])
                    ->unique(),
            );
            self::assertSame(
                1,
                DB::table('person_training_experiences')
                    ->where('person_id', $personId)
                    ->count(),
            );
            self::assertSame(
                1,
                DB::table('audit_events')
                    ->where('actor_id', $accountId)
                    ->where('action', 'people.training.added')
                    ->where('outcome', 'succeeded')
                    ->count(),
            );
            $claim = DB::table('person_training_idempotency')
                ->where('account_id', $accountId)
                ->where('person_id', $personId)
                ->where('idempotency_key', $idempotencyKey)
                ->sole();
            $ciphertext = (string) $claim->payload_encrypted;
            foreach ([
                'หลักสูตรพร้อมกัน',
                'ศูนย์พร้อมกัน',
                '2025-07-10',
                '2025-07-12',
            ] as $plaintextValue) {
                self::assertStringNotContainsString($plaintextValue, $ciphertext);
            }
            self::assertSame(
                [
                    'course_name' => 'หลักสูตรพร้อมกัน',
                    'provider_name' => 'ศูนย์พร้อมกัน',
                    'started_on' => '2025-07-10',
                    'ended_on' => '2025-07-12',
                ],
                json_decode(
                    (string) $this->app->make(Encrypter::class)->decrypt(
                        $ciphertext,
                        false,
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            );
        } finally {
            @unlink($resultFile);
            DB::table('person_training_idempotency')->where('account_id', $accountId)->delete();
            DB::table('audit_events')->where('actor_id', $accountId)->delete();
            DB::table('person_training_experiences')->where('person_id', $personId)->delete();
            DB::table('auth_sessions')->where('account_id', $accountId)->delete();
            DB::table('credentials')->where('account_id', $accountId)->delete();
            DB::table('accounts')->where('id', $accountId)->delete();
            DB::table('person_identifiers')->where('person_id', $personId)->delete();
            DB::table('people')->where('id', $personId)->delete();
            DB::connection()->beginTransaction();
        }
    }

    public function test_current_profile_edit_cannot_modify_immutable_application_snapshot(): void
    {
        [$accountId, $personId] = $this->createAccount('5234567890123');
        Schema::create('application_profile_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->json('profile');
        });
        $snapshotId = (string) Str::uuid();
        $snapshot = json_encode(['given_name' => 'ชื่อวันสมัคร'], JSON_THROW_ON_ERROR);
        DB::table('application_profile_snapshots')->insert([
            'id' => $snapshotId,
            'person_id' => $personId,
            'profile' => $snapshot,
        ]);

        $this->asAccount($accountId)
            ->putJson('/member/profile', [
                'given_name' => 'ชื่อปัจจุบัน',
                'family_name' => 'นามสกุลใหม่',
                'email' => '',
                'phone' => '',
                'version' => 1,
            ])->assertOk();

        self::assertSame(
            $snapshot,
            DB::table('application_profile_snapshots')->where('id', $snapshotId)->value('profile'),
        );
    }

    public function test_member_application_list_reads_only_owned_person_facts_and_has_explicit_empty_state(): void
    {
        [$accountId, $personId] = $this->createAccount('6234567890123');
        [, $otherPersonId] = $this->createAccount('7234567890123');
        $sessionOne = $this->seedCourseSession('D10-2026-A');
        $sessionTwo = $this->seedCourseSession('D10-2026-B');
        DB::table('application_workflow_facts')->insert([
            [
                'course_session_id' => $sessionOne,
                'person_id' => $personId,
                'state' => 'draft',
                'created_at' => '2026-07-28 08:00:00+00',
                'updated_at' => '2026-07-29 02:15:00+00',
            ],
            [
                'course_session_id' => $sessionTwo,
                'person_id' => $otherPersonId,
                'state' => 'submitted',
                'created_at' => '2026-07-28 08:00:00+00',
                'updated_at' => '2026-07-29 03:15:00+00',
            ],
        ]);

        $this->asAccount($accountId)
            ->get('/member/applications')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Member/Center', false)
                ->has('applications', 1)
                ->where('applications.0.courseSessionId', $sessionOne)
                ->where('applications.0.state', 'draft')
                ->where('applications.0.lastSavedAt', '2026-07-29 02:15:00+00')
                ->where('applications.0.nextTask', null)
                ->where(
                    'applications.0.nextTaskUnavailableReason',
                    'ยังไม่มีข้อมูลขั้นตอนถัดไปจากระบบใบสมัคร',
                )
                ->where('applications.0.resumeUrl', null)
                ->where(
                    'applications.0.resumeUnavailableReason',
                    'ยังไม่มีเส้นทางทำรายการต่อที่ได้รับอนุญาต',
                )
                ->where('applications.0.history.0.state', 'draft')
                ->where('applications.0.history.0.occurredAt', '2026-07-29 02:15:00+00'));
    }

    private function asAccount(string $accountId): self
    {
        $authSessionId = 'member-test-'.Str::random(18);
        DB::table('auth_sessions')->insert([
            'id' => $authSessionId,
            'account_id' => $accountId,
            'credential_epoch' => 1,
            'authenticated_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        return $this->actingAs(Account::query()->findOrFail($accountId))
            ->withSession(['identity_access.auth_session_id' => $authSessionId]);
    }

    /** @return array{string, string} */
    private function createAccount(string $identityNumber): array
    {
        $personId = $this->app->make(PersonIdentityDirectory::class)->create(
            IdentityClaim::fromInput('personal_id', $identityNumber),
            'ชื่อเดิม',
            'นามสกุลเดิม',
        );
        $accountId = (string) Str::uuid();
        $now = CarbonImmutable::now();
        $email = "{$accountId}@example.test";
        $keyVersion = (string) config('identity-access.account_lookup_key_version');
        $keys = config('identity-access.account_lookup_keys');
        $key = is_array($keys) ? ($keys[$keyVersion] ?? '') : '';
        DB::table('accounts')->insert([
            'id' => $accountId,
            'person_id' => $personId,
            'email_digest_key_version' => $keyVersion,
            'email_digest' => hash_hmac('sha256', "email:{$email}", $key),
            'email_encrypted' => encrypt($email),
            'status' => 'active',
            'credential_epoch' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('credentials')->insert([
            'account_id' => $accountId,
            'password_hash' => Hash::make('member-password-123'),
            'algorithm' => 'current',
            'changed_at' => $now,
        ]);

        return [$accountId, $personId];
    }

    private function seedThaiReferences(): void
    {
        DB::table('provinces')->insert([
            ['id' => 'p-bkk', 'code' => '10', 'name_th' => 'กรุงเทพมหานคร', 'name_en' => 'Bangkok', 'active' => true, 'display_order' => 1],
            ['id' => 'p-other', 'code' => '20', 'name_th' => 'จังหวัดอื่น', 'name_en' => 'Other', 'active' => true, 'display_order' => 2],
        ]);
        DB::table('amphoes')->insert([
            ['id' => 'a-phra', 'province_id' => 'p-bkk', 'code' => '1001', 'name_th' => 'พระนคร', 'name_en' => 'Phra Nakhon', 'active' => true, 'display_order' => 1],
            ['id' => 'a-other', 'province_id' => 'p-other', 'code' => '2001', 'name_th' => 'อำเภออื่น', 'name_en' => 'Other', 'active' => true, 'display_order' => 1],
        ]);
        DB::table('tambons')->insert([
            ['id' => 't-wat', 'amphoe_id' => 'a-phra', 'code' => '100101', 'name_th' => 'พระบรมมหาราชวัง', 'name_en' => 'Wat', 'postcode' => '10200', 'active' => true, 'display_order' => 1],
        ]);
    }

    private function seedCourseSession(string $code): string
    {
        $suffix = Str::lower(Str::random(8));
        $provinceId = "p-{$suffix}";
        $typeId = "type-{$suffix}";
        $centerId = "center-{$suffix}";
        $courseId = "course-{$suffix}";
        $sessionId = (string) Str::uuid();
        DB::table('provinces')->insert([
            'id' => $provinceId,
            'code' => Str::upper(Str::random(6)),
            'name_th' => 'จังหวัดทดสอบ',
            'name_en' => 'Test Province',
            'active' => true,
            'display_order' => 1,
        ]);
        DB::table('course_types')->insert([
            'id' => $typeId,
            'name_th' => 'หลักสูตรทดสอบ',
            'name_en' => 'Test course',
            'active' => true,
        ]);
        DB::table('centers')->insert([
            'id' => $centerId,
            'name_th' => 'ศูนย์ทดสอบ',
            'name_en' => 'Test center',
            'address_th' => 'ที่อยู่ทดสอบ',
            'province_id' => $provinceId,
            'map_url' => 'https://maps.example.test/local',
            'active' => true,
        ]);
        DB::table('courses')->insert([
            'id' => $courseId,
            'course_type_id' => $typeId,
            'title_th' => 'หลักสูตรทดสอบ',
            'summary_th' => 'รายละเอียดทดสอบ',
        ]);
        DB::table('course_sessions')->insert([
            'id' => $sessionId,
            'code' => $code,
            'course_id' => $courseId,
            'center_id' => $centerId,
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-01-12',
            'registration_opens_at' => CarbonImmutable::parse('2025-11-01'),
            'registration_closes_at' => CarbonImmutable::parse('2025-12-01'),
            'policy_version' => 'local-v1',
            'timezone' => 'Asia/Bangkok',
            'applicant_type' => 'member',
            'approved_categories' => json_encode(['member'], JSON_THROW_ON_ERROR),
            'invite_only' => false,
            'published' => true,
        ]);

        return $sessionId;
    }
}
