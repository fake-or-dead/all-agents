<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Modules\DocumentsConsent\Contracts\ConsentAcceptanceService;
use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\PrivacySafeRateLimiter;
use App\Rules\MaxUtf8Bytes;
use App\Rules\ValidIdentityClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class RegistrationController extends Controller
{
    private const NEUTRAL_MESSAGE = 'หากข้อมูลถูกต้อง ระบบได้ส่งวิธียืนยันให้แล้ว';

    public function __construct(
        private readonly IdentityAccessWorkflow $identityAccess,
        private readonly PrivacySafeRateLimiter $rateLimiter,
        private readonly ConsentAcceptanceService $consents,
    ) {}

    public function show(): Response
    {
        $consent = $this->consents->currentRegistrationConsent();

        return Inertia::render('Auth/Register', [
            'consent' => [
                'id' => $consent->id,
                'title' => $consent->title,
                'versionLabel' => $consent->versionLabel,
                'content' => $consent->content,
            ],
            'csrfToken' => csrf_token(),
        ]);
    }

    public function requestVerification(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $correlationId = (string) Str::uuid();

        $accepted = $this->rateLimiter->attemptEmail(
            'verification-request',
            (string) $request->ip(),
            $validated['email'],
            ['client' => 10, 'identifier' => 3, 'pair' => 3, 'decay' => 60],
            function () use ($correlationId, $validated): bool {
                $this->identityAccess->requestEmailVerification(
                    $validated['email'],
                    $correlationId,
                );

                return true;
            },
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
            'person_link_token' => ['nullable', 'string', 'min:32', 'max:128'],
            'identity_type' => ['required', Rule::in(['personal_id', 'passport'])],
            'identity_number' => [
                'required',
                'string',
                new ValidIdentityClaim($request->input('identity_type')),
            ],
            'given_name' => ['required', 'string', 'max:160'],
            'family_name' => ['required', 'string', 'max:160'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->numbers(),
                new MaxUtf8Bytes(72),
            ],
            'consent_accepted' => ['accepted'],
            'consent_version' => ['required', 'uuid'],
        ]);
        $result = $this->identityAccess->register($validated, (string) Str::uuid());

        if (! $result->successful) {
            return response()->json([
                'message' => 'ไม่สามารถสร้างบัญชีได้ กรุณาตรวจสอบข้อมูลหรือขอรหัสใหม่',
            ], 422);
        }

        $account = Account::query()->findOrFail($result->data['account_id']);
        $authSessionId = Str::random(64);
        try {
            $this->identityAccess->recordAuthenticatedSession(
                (string) $account->getAuthIdentifier(),
                $authSessionId,
                (int) $result->data['credential_epoch'],
            );
        } catch (\RuntimeException) {
            return response()->json([
                'message' => 'ไม่สามารถสร้างบัญชีได้ กรุณาตรวจสอบข้อมูลหรือขอรหัสใหม่',
            ], 422);
        }
        Auth::login($account);
        $request->session()->regenerate();
        $request->session()->put('identity_access.auth_session_id', $authSessionId);

        return $request->expectsJson()
            ? response()->json(['redirect' => route('account.home')], 201)
            : redirect()->route('account.home');
    }
}
