<?php

namespace Tests\Feature\IdentityAccess;

use App\Modules\IdentityAccess\Infrastructure\Verification\DeterministicFakeVerificationGateway;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
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
            'credential_epoch' => 1,
            'authenticated_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);
        $message = 'หากมีบัญชีที่ตรงกัน ระบบได้ส่งวิธีกู้คืนให้แล้ว';

        $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '1234567890123',
            'password' => 'old-password-123',
        ])->assertOk();
        $this->assertAuthenticated();

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
            ->assertNotFound();
        $token = basename($path);
        $this->get("/recover/{$token}")
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString(
            'no-store',
            (string) $this->get("/recover/{$token}")->headers->get('Cache-Control'),
        );

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
        $this->get('/account')->assertRedirect('/signin');
        $this->assertGuest();

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
        $personId = $this->app->make(PersonIdentityDirectory::class)->create(
            IdentityClaim::fromInput('personal_id', $identity),
            'ทดสอบ',
            'กู้คืน',
        );
        $accountId = (string) Str::uuid();
        $now = CarbonImmutable::now();
        $emailKeyVersion = (string) config('identity-access.account_lookup_key_version');
        $emailKeys = config('identity-access.account_lookup_keys');
        $emailKey = is_array($emailKeys) ? $emailKeys[$emailKeyVersion] : '';
        DB::table('accounts')->insert([
            'id' => $accountId,
            'person_id' => $personId,
            'email_digest_key_version' => $emailKeyVersion,
            'email_digest' => hash_hmac(
                'sha256',
                'email:'.mb_strtolower($email),
                $emailKey,
            ),
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
