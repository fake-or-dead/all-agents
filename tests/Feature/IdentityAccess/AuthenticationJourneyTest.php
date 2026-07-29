<?php

namespace Tests\Feature\IdentityAccess;

use App\Models\Account;
use App\Modules\IdentityAccess\Infrastructure\PrivacySafeRateLimiter;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthenticationJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_is_neutral_rotates_session_restores_destination_and_signs_out_with_post(): void
    {
        $accountId = $this->createAccount('personal_id', '1234567890123', 'correct-password-123');
        $message = 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง หรือบัญชีต้องกู้คืนการเข้าถึง';

        $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '9999999999999',
            'password' => 'wrong-password-123',
        ])->assertUnprocessable()->assertExactJson(['message' => $message]);
        $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '1234567890123',
            'password' => 'wrong-password-123',
        ])->assertUnprocessable()->assertExactJson(['message' => $message]);

        $this->get('/signin?intended=/account')->assertOk();
        $response = $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '1234567890123',
            'password' => 'correct-password-123',
        ])->assertOk()->assertJsonPath('redirect', '/account');

        $this->assertAuthenticatedAs(Account::query()->findOrFail($accountId));
        $this->assertDatabaseHas('auth_sessions', [
            'account_id' => $accountId,
            'revoked_at' => null,
        ]);
        $this->get('/signout')->assertMethodNotAllowed();
        $this->postJson('/signout')->assertOk();
        $this->assertGuest();
        $this->assertDatabaseHas('auth_sessions', [
            'account_id' => $accountId,
            'revoked_reason' => 'sign_out',
        ]);
    }

    public function test_supported_legacy_hash_rehashes_on_success_and_unsupported_hash_uses_neutral_failure(): void
    {
        $legacyId = $this->createAccount(
            'passport',
            'AB123456',
            'legacy-password-123',
            'legacy_bcrypt',
        );
        $unsupportedId = $this->createAccount(
            'passport',
            'CD123456',
            'unused-password-123',
            'unsupported',
        );
        $oldHash = DB::table('credentials')->where('account_id', $legacyId)->value('password_hash');

        $this->postJson('/signin', [
            'identity_type' => 'passport',
            'identity_number' => 'ab 123456',
            'password' => 'legacy-password-123',
        ])->assertOk();

        $credential = DB::table('credentials')->where('account_id', $legacyId)->first();
        $this->assertSame('current', $credential->algorithm);
        $this->assertNotSame($oldHash, $credential->password_hash);
        $this->assertTrue(Hash::check('legacy-password-123', $credential->password_hash));

        $this->postJson('/signout')->assertOk();
        $neutral = 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง หรือบัญชีต้องกู้คืนการเข้าถึง';
        $this->postJson('/signin', [
            'identity_type' => 'passport',
            'identity_number' => 'CD123456',
            'password' => 'unused-password-123',
        ])->assertUnprocessable()->assertExactJson(['message' => $neutral]);
        $this->assertDatabaseHas('credentials', [
            'account_id' => $unsupportedId,
            'algorithm' => 'unsupported',
        ]);
    }

    public function test_protected_route_captures_only_safe_intended_destination_once(): void
    {
        $this->createAccount('passport', 'RT123456', 'correct-password-123');

        $this->get('/account?section=password')->assertRedirect('/signin');
        $this->postJson('/signin', [
            'identity_type' => 'passport',
            'identity_number' => 'RT123456',
            'password' => 'correct-password-123',
        ])->assertOk()->assertJsonPath('redirect', '/account?section=password');

        $this->postJson('/signout')->assertOk();
        $this->get('/signin?intended=//evil.example')->assertOk();
        $this->postJson('/signin', [
            'identity_type' => 'passport',
            'identity_number' => 'RT123456',
            'password' => 'correct-password-123',
        ])->assertOk()->assertJsonPath('redirect', '/account');
    }

    public function test_sign_in_throttles_failures_without_exposing_account_existence(): void
    {
        $this->createAccount('personal_id', '1111111111111', 'correct-password-123');

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/signin', [
                'identity_type' => 'personal_id',
                'identity_number' => '1111111111111',
                'password' => 'wrong-password-123',
            ])->assertUnprocessable();
        }

        $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '1111111111111',
            'password' => 'correct-password-123',
        ])->assertTooManyRequests();
        $this->assertGuest();
        $denialReasons = DB::table('audit_events')
            ->where('action', 'account.sign_in')
            ->where('outcome', 'denied')
            ->pluck('context')
            ->map(static fn (string $context): string => json_decode(
                $context,
                true,
                flags: JSON_THROW_ON_ERROR,
            )['reason'])
            ->all();
        $this->assertContains('rate_limited', $denialReasons);
    }

    public function test_client_bucket_cannot_be_bypassed_by_rotating_identifiers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.41']);

        foreach (range(1, 20) as $attempt) {
            $this->postJson('/signin', [
                'identity_type' => 'passport',
                'identity_number' => sprintf('ROTATE%06d', $attempt),
                'password' => 'wrong-password-123',
            ])->assertUnprocessable();
        }

        $this->postJson('/signin', [
            'identity_type' => 'passport',
            'identity_number' => 'ROTATE999999',
            'password' => 'wrong-password-123',
        ])->assertTooManyRequests();
    }

    public function test_identifier_bucket_cannot_be_bypassed_by_rotating_clients(): void
    {
        $this->createAccount('passport', 'TARGET12345', 'correct-password-123');

        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
                ->postJson('/signin', [
                    'identity_type' => 'passport',
                    'identity_number' => 'TARGET12345',
                    'password' => 'wrong-password-123',
                ])->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->postJson('/signin', [
                'identity_type' => 'passport',
                'identity_number' => 'TARGET12345',
                'password' => 'wrong-password-123',
            ])->assertTooManyRequests();
    }

    public function test_real_redis_parallel_requests_cannot_overrun_an_overlapping_client_bucket(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            self::markTestSkipped('pcntl is required for the real-Redis concurrency check.');
        }

        // phpunit defaults to array cache; deliberately switch this one test
        // to the Compose Redis service so process isolation is real.
        config()->set('cache.default', 'redis');
        Cache::clearResolvedInstance('cache');
        RateLimiter::clearResolvedInstance('cache.rateLimiter');
        Cache::clear();
        $resultFile = tempnam(sys_get_temp_dir(), 'tapoda-rate-');
        self::assertNotFalse($resultFile);
        $children = [];

        foreach (range(1, 4) as $attempt) {
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                // Forking inherits the parent's phpredis socket. Discard each
                // resolved cache service so every child opens its own real
                // Redis connection before contending on the distributed lock.
                Cache::clearResolvedInstance('cache');
                RateLimiter::clearResolvedInstance('cache.rateLimiter');
                $this->app->forgetInstance('cache');
                $this->app->forgetInstance('cache.rateLimiter');

                $accepted = $this->app->make(PrivacySafeRateLimiter::class)->attemptIdentity(
                    'parallel-client-ceiling',
                    '198.51.100.200',
                    IdentityClaim::fromInput('passport', sprintf('PARALLEL%04d', $attempt)),
                    ['client' => 1, 'identifier' => 10, 'pair' => 10, 'decay' => 60],
                    static function () use ($resultFile): bool {
                        file_put_contents($resultFile, "1\n", FILE_APPEND | LOCK_EX);

                        return true;
                    },
                );

                // Do not run Laravel/PHPUnit shutdown handlers in the forked
                // child; RefreshDatabase teardown would race the parent
                // against the same PostgreSQL schema. The callback has
                // already written/closed its result file.
                posix_kill(posix_getpid(), SIGKILL);
            }

            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifsignaled($status));
        }

        self::assertCount(1, file($resultFile, FILE_IGNORE_NEW_LINES));
        @unlink($resultFile);
        Cache::clear();
        // Restore PHPUnit's default cache and clear test-local audit state;
        // the forked child deliberately bypasses normal Laravel teardown.
        config()->set('cache.default', 'array');
        Cache::clearResolvedInstance('cache');
        RateLimiter::clearResolvedInstance('cache.rateLimiter');
        DB::table('audit_events')->truncate();
    }

    public function test_sign_in_validation_is_thai_and_does_not_query_malformed_identity(): void
    {
        $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '123',
            'password' => 'any-password-123',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.identity_number.0',
                'ข้อมูลประจำตัวไม่ถูกต้อง',
            );

        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_password_change_uses_post_and_revokes_other_sessions(): void
    {
        $accountId = $this->createAccount(
            'personal_id',
            '2222222222222',
            'current-password-123',
        );
        $this->postJson('/signin', [
            'identity_type' => 'personal_id',
            'identity_number' => '2222222222222',
            'password' => 'current-password-123',
        ])->assertOk();
        DB::table('auth_sessions')->insert([
            'id' => 'another-device-session',
            'account_id' => $accountId,
            'credential_epoch' => 1,
            'authenticated_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        $this->postJson('/account/password', [
            'current_password' => 'current-password-123',
            'password' => 'replacement-password-456',
            'password_confirmation' => 'replacement-password-456',
        ])->assertOk()->assertExactJson(['message' => 'เปลี่ยนรหัสผ่านแล้ว']);

        $this->assertTrue(Hash::check(
            'replacement-password-456',
            DB::table('credentials')->where('account_id', $accountId)->value('password_hash'),
        ));
        $this->assertDatabaseHas('auth_sessions', [
            'id' => 'another-device-session',
            'revoked_reason' => 'credential_change',
        ]);
        $this->get('/account/password')->assertMethodNotAllowed();

        $this->postJson('/account/password', [
            'current_password' => 'wrong-current-password',
            'password' => 'replacement-password-789',
            'password_confirmation' => 'replacement-password-789',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'account.password.changed',
            'outcome' => 'denied',
        ]);
    }

    private function createAccount(
        string $identityType,
        string $identityNumber,
        string $password,
        string $algorithm = 'current',
    ): string {
        $personId = $this->app->make(PersonIdentityDirectory::class)->create(
            IdentityClaim::fromInput($identityType, $identityNumber),
            'ทดสอบ',
            'ระบบ',
        );
        $accountId = (string) Str::uuid();
        $now = CarbonImmutable::now();
        $email = "{$accountId}@example.test";
        $emailKeyVersion = (string) config('identity-access.account_lookup_key_version');
        $emailKeys = config('identity-access.account_lookup_keys');
        $emailKey = is_array($emailKeys) ? $emailKeys[$emailKeyVersion] : '';
        DB::table('accounts')->insert([
            'id' => $accountId,
            'person_id' => $personId,
            'email_digest_key_version' => $emailKeyVersion,
            'email_digest' => hash_hmac('sha256', "email:{$email}", $emailKey),
            'email_encrypted' => encrypt($email),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('credentials')->insert([
            'account_id' => $accountId,
            'password_hash' => Hash::make($password),
            'algorithm' => $algorithm,
            'changed_at' => $now,
        ]);

        return $accountId;
    }
}
