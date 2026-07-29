<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\PrivacySafeRateLimiter;
use App\Rules\MaxUtf8Bytes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class RecoveryController extends Controller
{
    private const NEUTRAL_MESSAGE = 'หากมีบัญชีที่ตรงกัน ระบบได้ส่งวิธีกู้คืนให้แล้ว';

    public function __construct(
        private readonly IdentityAccessWorkflow $identityAccess,
        private readonly PrivacySafeRateLimiter $rateLimiter,
    ) {}

    public function showRequest(): Response
    {
        return Inertia::render('Auth/Forgot', ['csrfToken' => csrf_token()]);
    }

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $correlationId = (string) Str::uuid();

        $accepted = $this->rateLimiter->attemptEmail(
            'recovery-request',
            (string) $request->ip(),
            $validated['email'],
            ['client' => 10, 'identifier' => 3, 'pair' => 3, 'decay' => 60],
            function () use ($correlationId, $validated): bool {
                $this->identityAccess->requestRecovery(
                    $validated['email'],
                    $correlationId,
                );

                return true;
            },
        );

        if (! $accepted) {
            $this->identityAccess->recordRateLimited(
                'account.recovery.requested',
                $correlationId,
            );
        }

        return response()->json(['message' => self::NEUTRAL_MESSAGE], 202);
    }

    public function showRedeem(string $token): Response
    {
        return Inertia::render('Auth/Recover', [
            'token' => $token,
            'csrfToken' => csrf_token(),
        ]);
    }

    public function redeem(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->numbers(),
                new MaxUtf8Bytes(72),
            ],
        ]);
        $result = $this->identityAccess->redeemRecovery(
            $token,
            $validated['password'],
            (string) Str::uuid(),
        );

        if (! $result->successful) {
            return response()->json([
                'message' => 'ลิงก์กู้คืนไม่ถูกต้องหรือหมดอายุแล้ว',
            ], 422);
        }

        return $request->expectsJson()
            ? response()->json(['redirect' => '/signin'])
            : redirect()->route('auth.sign-in')->with('status', 'ตั้งรหัสผ่านใหม่แล้ว');
    }
}
