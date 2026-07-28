<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class RegistrationController extends Controller
{
    private const NEUTRAL_MESSAGE = 'หากข้อมูลถูกต้อง ระบบได้ส่งวิธียืนยันให้แล้ว';

    public function __construct(private readonly IdentityAccessWorkflow $identityAccess) {}

    public function show(): Response
    {
        return Inertia::render('Auth/Register', [
            'consentVersion' => (string) config('identity-access.registration_consent_version'),
            'csrfToken' => csrf_token(),
        ]);
    }

    public function requestVerification(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $rateKey = 'verification-request:'.$request->ip().':'.hash(
            'sha256',
            mb_strtolower(trim($validated['email'])),
        );
        $correlationId = (string) Str::uuid();

        $accepted = RateLimiter::attempt(
            $rateKey,
            3,
            function () use ($correlationId, $validated): bool {
                $this->identityAccess->requestEmailVerification(
                    $validated['email'],
                    $correlationId,
                );

                return true;
            },
            60,
        );

        if (! $accepted) {
            $this->identityAccess->recordRateLimited(
                'identity.verification.requested',
                $correlationId,
            );

            return response()->json(['message' => self::NEUTRAL_MESSAGE], 429);
        }

        return response()->json(['message' => self::NEUTRAL_MESSAGE], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'code' => ['required', 'string', 'size:6'],
        ]);
        $result = $this->identityAccess->verifyEmail(
            $validated['email'],
            $validated['code'],
            (string) Str::uuid(),
        );

        if (! $result->successful) {
            return response()->json([
                'message' => 'ไม่สามารถยืนยันได้ กรุณาขอรหัสใหม่',
            ], 422);
        }

        return response()->json($result->data);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'registration_token' => ['required', 'string', 'min:32', 'max:128'],
            'identity_type' => ['required', Rule::in(['personal_id', 'passport'])],
            'identity_number' => [
                'required',
                'string',
                Rule::when(
                    $request->input('identity_type') === 'personal_id',
                    ['regex:/^\d{13}$/'],
                    ['regex:/^[A-Za-z0-9 -]{6,24}$/'],
                ),
            ],
            'given_name' => ['required', 'string', 'max:160'],
            'family_name' => ['required', 'string', 'max:160'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'consent_accepted' => ['accepted'],
            'consent_version' => [
                'required',
                Rule::in([(string) config('identity-access.registration_consent_version')]),
            ],
        ]);
        $result = $this->identityAccess->register($validated, (string) Str::uuid());

        if (! $result->successful) {
            return response()->json([
                'message' => 'ไม่สามารถสร้างบัญชีได้ กรุณาตรวจสอบข้อมูลหรือขอรหัสใหม่',
            ], 422);
        }

        $account = Account::query()->findOrFail($result->data['account_id']);
        Auth::login($account);
        $request->session()->regenerate();
        $authSessionId = Str::random(64);
        $request->session()->put('identity_access.auth_session_id', $authSessionId);
        $this->identityAccess->recordAuthenticatedSession(
            (string) $account->getAuthIdentifier(),
            $authSessionId,
        );

        return $request->expectsJson()
            ? response()->json(['redirect' => route('account.home')], 201)
            : redirect()->route('account.home');
    }
}
