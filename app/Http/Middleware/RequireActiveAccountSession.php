<?php

namespace App\Http\Middleware;

use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use App\Modules\IdentityAccess\Infrastructure\SafeIntendedDestination;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireActiveAccountSession
{
    public function __construct(
        private IdentityAccessWorkflow $identityAccess,
        private SafeIntendedDestination $destinations,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $account = Auth::user();
        $authSessionId = $request->session()->get('identity_access.auth_session_id');

        if ($account === null) {
            if ($request->routeIs('account.*', 'member.*')) {
                $destination = $request->routeIs('member.*')
                    ? $this->destinations->memberPath($request->getRequestUri())
                    : $this->destinations->accountPath($request->getRequestUri());

                if ($destination !== null) {
                    $request->session()->put('url.intended', $destination);
                }

                return $request->expectsJson()
                    ? response()->json(['message' => 'กรุณาเข้าสู่ระบบ'], 401)
                    : redirect()->route('auth.sign-in');
            }

            return $next($request);
        }

        if (
            ! is_string($authSessionId)
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
