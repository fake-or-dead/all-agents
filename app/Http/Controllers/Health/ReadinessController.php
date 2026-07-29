<?php

namespace App\Http\Controllers\Health;

use App\Modules\PlatformOperations\Contracts\ReadinessChecks;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReadinessController
{
    public function __construct(private ReadinessChecks $checks) {}

    public function __invoke(): JsonResponse
    {
        $report = $this->checks->run();
        $status = $report->ready() ? 'ready' : 'degraded';

        return response()
            ->json([
                'status' => $status,
                'build' => [
                    'version' => (string) config('platform.build.version'),
                    'commit' => substr((string) config('platform.build.commit'), 0, 12),
                ],
                'checks' => [
                    'database' => $report->database ? 'ok' : 'failed',
                    'redis' => $report->redis ? 'ok' : 'failed',
                    'queue' => $report->queue ? 'ok' : 'stale',
                    'scheduler' => $report->scheduler ? 'ok' : 'stale',
                    'migrations' => [
                        'status' => $report->pendingMigrations === 0 ? 'ok' : 'pending',
                        'pending' => $report->pendingMigrations,
                    ],
                ],
            ], $report->ready() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Cache-Control', 'no-store');
    }
}
