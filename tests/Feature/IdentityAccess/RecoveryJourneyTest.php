<?php

namespace Tests\Feature\IdentityAccess;

use App\Modules\IdentityAccess\Infrastructure\Verification\DeterministicFakeVerificationGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RecoveryJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_is_neutral_preserves_current_credential_until_one_use_redemption_and_revokes_sessions(): void
    {
        $email = 'recover@example.test';
        $accountId = $this->createAccount($email, '1234567890123', 'old-password-123');
        $originalHash = DB::table('credentials')->where('account_id', $accountId)->value('password_hash');
        DB::table('auth_sessions')->insert([
            'id' => 'old-session',
            'account_id' => $accountId,
            'authenticated_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);
        $message = 'หากมีบัญชีที่ตรงกัน ระบบได้ส่งวิธีกู้คืนให้แล้ว';

        $this->postJson('/forgot', ['email' => 'unknown@example.test'])
            ->assertAccepted()
            ->assertExactJson(['message' => $message]);
        $this->postJson('/forgot', ['email' => $email])
            ->assertAccepted()
            ->assertExactJson(['message' => $message]);
        $this->assertSame(
            $originalHash,
            DB::table('credentials')->where('account_id', $accountId)->value('password_hash'),
        );

        $path = $this->app
            ->make(DeterministicFakeVerificationGateway::class)
            ->latestRecoveryPathFor($email);
        $this->assertNotNull($path);
        $this->getJson('/local/verification-mailbox/recovery?email='.urlencode($email))
            ->assertOk()
            ->assertExactJson(['path' => $path]);
        $token = basename($path);

        $this->postJson("/recover/{$token}", [
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertOk()->assertJsonPath('redirect', '/signin');

        $credential = DB::table('credentials')->where('account_id', $accountId)->first();
        $this->assertTrue(Hash::check('new-password-456', $credential->password_hash));
        $this->assertDatabaseHas('auth_sessions', [
            'id' => 'old-session',
            'revoked_reason' => 'credential_recovery',
        ]);

        $this->postJson("/recover/{$token}", [
            'password' => 'another-password-789',
            'password_confirmation' => 'another-password-789',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'account.recovery.redeemed',
            'outcome' => 'denied',
        ]);
        $this->assertTrue(Hash::check(
            'new-password-456',
            DB::table('credentials')->where('account_id', $accountId)->value('password_hash'),
        ));
    }

    public function test_expired_recovery_token_does_not_change_the_credential(): void
    {
        $email = 'expired@example.test';
        $accountId = $this->createAccount($email, '1234567890123', 'old-password-123');
        $this->postJson('/forgot', ['email' => $email])->assertAccepted();
        $path = $this->app
            ->make(DeterministicFakeVerificationGateway::class)
            ->latestRecoveryPathFor($email);
        $this->assertNotNull($path);
        $token = basename($path);
        $before = DB::table('credentials')->where('account_id', $accountId)->value('password_hash');
        DB::table('verification_challenges')
            ->where('purpose', 'recovery')
            ->update(['expires_at' => CarbonImmutable::now()->subSecond()]);

        $this->postJson("/recover/{$token}", [
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'account.recovery.redeemed',
            'outcome' => 'denied',
        ]);

        $this->assertSame(
            $before,
            DB::table('credentials')->where('account_id', $accountId)->value('password_hash'),
        );
    }

    private function createAccount(string $email, string $identity, string $password): string
    {
        $personId = (string) Str::uuid();
        $accountId = (string) Str::uuid();
        $now = CarbonImmutable::now();
        $key = (string) config('identity-access.identifier_key');

        DB::table('people')->insert([
            'id' => $personId,
            'given_name' => 'ทดสอบ',
            'family_name' => 'กู้คืน',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('person_identifiers')->insert([
            'id' => (string) Str::uuid(),
            'person_id' => $personId,
            'type' => 'personal_id',
            'country_code' => 'TH',
            'identifier_encrypted' => encrypt($identity),
            'lookup_digest' => hash_hmac('sha256', "personal_id:{$identity}", $key),
            'last_four' => mb_substr($identity, -4),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('accounts')->insert([
            'id' => $accountId,
            'person_id' => $personId,
            'email_digest' => hash_hmac('sha256', 'email:'.mb_strtolower($email), $key),
            'email_encrypted' => encrypt($email),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('credentials')->insert([
            'account_id' => $accountId,
            'password_hash' => Hash::make($password),
            'algorithm' => 'current',
            'changed_at' => $now,
        ]);

        return $accountId;
    }
}
