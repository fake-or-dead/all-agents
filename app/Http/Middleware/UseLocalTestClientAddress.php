<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UseLocalTestClientAddress
{
    public function handle(Request $request, Closure $next): Response
    {
        $testClient = $request->header('X-Tapoda-Test-Client');
        if (
            app()->environment(['local', 'testing'])
            && config('identity-access.verification_adapter') === 'deterministic-fake'
            && is_string($testClient)
            && preg_match('/\A[a-zA-Z0-9._:-]{8,128}\z/', $testClient) === 1
        ) {
            $request->server->set('REMOTE_ADDR', "local-test:{$testClient}");
        }

        return $next($request);
    }
}
