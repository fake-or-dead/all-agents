<?php

namespace App\Http\Middleware;

use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireActiveAccountSession
{
    public function __construct(private IdentityAccessWorkflow $identityAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = Auth::user();
        $authSessionId = $request->session()->get('identity_access.auth_session_id');

        if (
            $account === null
            || ! is_string($authSessionId)
            || $authSessionId === ''
            || ! $this->identityAccess->touchSession(
                (string) $account->getAuthIdentifier(),
                $authSessionId,
            )
        ) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'กรุณาเข้าสู่ระบบ'], 401)
                : redirect()->route('auth.sign-in');
        }

        return $next($request);
    }
}
