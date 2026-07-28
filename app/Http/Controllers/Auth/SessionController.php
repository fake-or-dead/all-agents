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
use Inertia\Inertia;
use Inertia\Response;

final class SessionController extends Controller
{
    public function __construct(private readonly IdentityAccessWorkflow $identityAccess) {}

    public function show(Request $request): Response
    {
        $intended = $request->query('intended');

        if (
            is_string($intended)
            && str_starts_with($intended, '/')
            && ! str_starts_with($intended, '//')
        ) {
            $request->session()->put('url.intended', $intended);
        }

        return Inertia::render('Auth/SignIn', ['csrfToken' => csrf_token()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
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
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $rateKey = 'sign-in:'.$request->ip().':'.hash(
            'sha256',
            $validated['identity_type'].':'.mb_strtoupper(trim($validated['identity_number'])),
        );
        $correlationId = (string) Str::uuid();

        if (! RateLimiter::attempt($rateKey, 5, static fn (): bool => true, 60)) {
            $this->identityAccess->recordRateLimited('account.sign_in', $correlationId);

            return response()->json([
                'message' => 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่ภายหลัง',
            ], 429);
        }

        $result = $this->identityAccess->authenticate(
            $validated['identity_type'],
            $validated['identity_number'],
            $validated['password'],
            $correlationId,
        );

        if (! $result->successful) {
            return response()->json([
                'message' => 'ข้อมูลเข้าสู่ระบบไม่ถูกต้อง หรือบัญชีต้องกู้คืนการเข้าถึง',
            ], 422);
        }

        RateLimiter::clear($rateKey);
        $account = Account::query()->findOrFail($result->data['account_id']);
        Auth::login($account);
        $request->session()->regenerate();
        $authSessionId = Str::random(64);
        $request->session()->put('identity_access.auth_session_id', $authSessionId);
        $this->identityAccess->recordAuthenticatedSession(
            (string) $account->getAuthIdentifier(),
            $authSessionId,
        );
        $destination = $request->session()->pull('url.intended', route('account.home'));

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
