<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProtectRecoveryTokenResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('recover/*')) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private',
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
