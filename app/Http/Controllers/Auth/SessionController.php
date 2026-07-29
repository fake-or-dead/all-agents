<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\PrivacySafeRateLimiter;
use App\Modules\IdentityAccess\Infrastructure\SafeIntendedDestination;
use App\Modules\People\Data\IdentityClaim;
use App\Rules\MaxUtf8Bytes;
use App\Rules\ValidIdentityClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class SessionController extends Controller
{
    public function __construct(
        private readonly IdentityAccessWorkflow $identityAccess,
        private readonly PrivacySafeRateLimiter $rateLimiter,
        private readonly SafeIntendedDestination $destinations,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Auth/SignIn', ['csrfToken' => csrf_token()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'identity_type' => ['required', Rule::in(['personal_id', 'passport'])],
            'identity_number' => [
                'required',
                'string',
                new ValidIdentityClaim($request->input('identity_type')),
            ],
            'password' => ['required', 'string', new MaxUtf8Bytes(72)],
        ]);
        $identity = IdentityClaim::fromInput(
            $validated['identity_type'],
            $validated['identity_number'],
        );
        $correlationId = (string) Str::uuid();

        if (! $this->rateLimiter->attemptIdentity(
            'sign-in',
            (string) $request->ip(),
            $identity,
            ['client' => 20, 'identifier' => 5, 'pair' => 5, 'decay' => 60],
            static fn (): bool => true,
        )) {
            $this->identityAccess->recordRateLimited('account.sign_in', $correlationId);

            return response()->json([
                'message' => 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่ภายหลัง',
            ], 429);
        }

        $authSessionId = Str::random(64);
        $result = $this->identityAccess->authenticate(
            $validated['identity_type'],
            $validated['identity_number'],
            $validated['password'],
            $authSessionId,
            $correlationId,
        );

        if (! $result->successful) {
            return response()->json([
                'message' => 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง หรือบัญชีต้องกู้คืนการเข้าถึง',
            ], 422);
        }

        $this->rateLimiter->clearIdentityFailures(
            'sign-in',
            (string) $request->ip(),
            $identity,
        );
        $account = Account::query()->findOrFail($result->data['account_id']);
        Auth::login($account);
        $request->session()->regenerate();
        $request->session()->put('identity_access.auth_session_id', $authSessionId);
        if (! $this->identityAccess->touchSession(
            (string) $account->getAuthIdentifier(),
            $authSessionId,
        )) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง หรือบัญชีต้องกู้คืนการเข้าถึง',
            ], 422);
        }
        $storedDestination = $request->session()->pull('url.intended');
        $destination = is_string($storedDestination)
            ? $this->destinations->accountPath($storedDestination)
            : null;
        $destination ??= route('account.home', absolute: false);

        return $request->expectsJson()
            ? response()->json(['redirect' => $destination])
            : redirect()->to($destination);
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $accountId = (string) Auth::id();
        $this->identityAccess->signOut(
            $accountId,
            (string) $request->session()->get('identity_access.auth_session_id', ''),
            (string) Str::uuid(),
        );
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['redirect' => '/signin'])
            : redirect()->route('auth.sign-in');
    }
}
