<?php

namespace Tests\Feature;

use App\Modules\PlatformOperations\Contracts\ReadinessChecks;
use App\Modules\PlatformOperations\Data\ReadinessReport;
use Tests\TestCase;

final class HealthEndpointsTest extends TestCase
{
    public function test_liveness_exposes_only_safe_build_identity(): void
    {
        config()->set('platform.build.version', 'issue-09-test');
        config()->set('platform.build.commit', '0123456789abcdef');

        $response = $this->getJson('/health/live');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeaderMissing('Set-Cookie')
            ->assertExactJson([
                'status' => 'ok',
                'build' => [
                    'version' => 'issue-09-test',
                    'commit' => '0123456789ab',
                ],
            ]);

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('host', $response->getContent());
    }

    public function test_readiness_exposes_safe_dependency_status_without_connection_details(): void
    {
        config()->set('platform.build.version', 'issue-09-test');
        config()->set('platform.build.commit', '0123456789abcdef');
        $this->app->instance(ReadinessChecks::class, new class implements ReadinessChecks
        {
            public function run(): ReadinessReport
            {
                return new ReadinessReport(
                    database: true,
                    redis: true,
                    queue: true,
                    scheduler: true,
                    pendingMigrations: 0,
                );
            }
        });

        $response = $this->getJson('/health/ready');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeaderMissing('Set-Cookie')
            ->assertExactJson([
                'status' => 'ready',
                'build' => [
                    'version' => 'issue-09-test',
                    'commit' => '0123456789ab',
                ],
                'checks' => [
                    'database' => 'ok',
                    'redis' => 'ok',
                    'queue' => 'ok',
                    'scheduler' => 'ok',
                    'migrations' => [
                        'status' => 'ok',
                        'pending' => 0,
                    ],
                ],
            ]);

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('host', $response->getContent());
        $this->assertStringNotContainsString('dsn', $response->getContent());
    }
}
