<?php

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;

final class LivenessController
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json([
                'status' => 'ok',
                'build' => [
                    'version' => (string) config('platform.build.version'),
                    'commit' => substr((string) config('platform.build.commit'), 0, 12),
                ],
            ])
            ->header('Cache-Control', 'no-store');
    }
}
