<?php

namespace Tests\Feature\Member;

use App\Models\Account;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Contracts\ProfileActivityRecorder;
use App\Modules\People\Data\IdentityClaim;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MemberMutationAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_member_mutations_roll_back_when_required_audit_append_fails(): void
    {
        [$profileAccountId, $profilePersonId] = $this->createAccount('2334567890123');
        [$addressAccountId, $addressPersonId] = $this->createAccount('2434567890123');
        [$addTrainingAccountId, $addTrainingPersonId] = $this->createAccount('2534567890123');
        [$updateTrainingAccountId, $updateTrainingPersonId] = $this->createAccount('2634567890123');
        $this->seedThaiReferences();

        $trainingId = (string) Str::uuid();
        DB::table('person_training_experiences')->insert([
            'id' => $trainingId,
            'person_id' => $updateTrainingPersonId,
            'course_name_encrypted' => encrypt('หลักสูตรเดิม'),
            'provider_name_encrypted' => encrypt('ผู้จัดเดิม'),
            'started_on' => '2024-01-10',
            'ended_on' => '2024-01-12',
            'version' => 1,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
        $auditCount = DB::table('audit_events')->count();

        $this->app->bind(
            ProfileActivityRecorder::class,
            fn (): ProfileActivityRecorder => new class implements ProfileActivityRecorder
            {
                public function record(
                    string $accountId,
                    string $personId,
                    string $action,
                    string $outcome,
                    string $correlationId,
                    array $context = [],
                ): void {
                    throw new \RuntimeException('Injected audit append failure.');
                }
            },
        );

        $this->asAccount($profileAccountId)
            ->putJson('/member/profile', [
                'given_name' => 'ชื่อที่ต้องย้อนกลับ',
                'family_name' => 'นามสกุลที่ต้องย้อนกลับ',
                'email' => 'rollback@example.test',
                'phone' => '0812345678',
                'version' => 1,
            ])->assertInternalServerError();
        $this->assertDatabaseHas('people', [
            'id' => $profilePersonId,
            'given_name' => 'ชื่อเดิม',
            'family_name' => 'นามสกุลเดิม',
            'profile_version' => 1,
        ]);
        $this->assertDatabaseMissing('person_contacts', ['person_id' => $profilePersonId]);

        $this->asAccount($addressAccountId)
            ->putJson('/member/address', [
                'address_line_1' => '99 ถนนที่ต้องย้อนกลับ',
                'address_line_2' => null,
                'province_id' => 'p-bkk',
                'amphoe_id' => 'a-phra',
                'tambon_id' => 't-wat',
                'version' => 0,
            ])->assertInternalServerError();
        $this->assertDatabaseMissing('person_addresses', ['person_id' => $addressPersonId]);

        $this->asAccount($addTrainingAccountId)
            ->withHeader('Idempotency-Key', 'training-atomicity-add')
            ->postJson('/member/training', [
                'course_name' => 'หลักสูตรที่ต้องย้อนกลับ',
                'provider_name' => 'ผู้จัดที่ต้องย้อนกลับ',
                'started_on' => '2025-01-10',
                'ended_on' => '2025-01-12',
            ])->assertInternalServerError();
        $this->assertDatabaseMissing('person_training_experiences', [
            'person_id' => $addTrainingPersonId,
        ]);

        $this->asAccount($updateTrainingAccountId)
            ->putJson("/member/training/{$trainingId}", [
                'course_name' => 'หลักสูตรใหม่ที่ต้องย้อนกลับ',
                'provider_name' => 'ผู้จัดใหม่ที่ต้องย้อนกลับ',
                'started_on' => '2025-02-10',
                'ended_on' => '2025-02-12',
                'version' => 1,
            ])->assertInternalServerError();
        $training = DB::table('person_training_experiences')->where('id', $trainingId)->first();
        self::assertNotNull($training);
        self::assertSame('หลักสูตรเดิม', decrypt($training->course_name_encrypted));
        self::assertSame('ผู้จัดเดิม', decrypt($training->provider_name_encrypted));
        self::assertSame('2024-01-10', (string) $training->started_on);
        self::assertSame('2024-01-12', (string) $training->ended_on);
        self::assertSame(1, (int) $training->version);
        self::assertSame($auditCount, DB::table('audit_events')->count());
    }

    private function asAccount(string $accountId): self
    {
        $authSessionId = 'member-atomicity-'.Str::random(18);
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
        ]);
        DB::table('amphoes')->insert([
            ['id' => 'a-phra', 'province_id' => 'p-bkk', 'code' => '1001', 'name_th' => 'พระนคร', 'name_en' => 'Phra Nakhon', 'active' => true, 'display_order' => 1],
        ]);
        DB::table('tambons')->insert([
            ['id' => 't-wat', 'amphoe_id' => 'a-phra', 'code' => '100101', 'name_th' => 'พระบรมมหาราชวัง', 'name_en' => 'Wat', 'postcode' => '10200', 'active' => true, 'display_order' => 1],
        ]);
    }
}
