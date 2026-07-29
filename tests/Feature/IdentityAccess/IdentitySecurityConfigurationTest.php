<?php

namespace Tests\Feature\IdentityAccess;

use App\Modules\IdentityAccess\Infrastructure\IdentitySecurityConfiguration;
use RuntimeException;
use Tests\TestCase;

final class IdentitySecurityConfigurationTest extends TestCase
{
    public function test_enabled_identity_routes_require_distinct_versioned_keys(): void
    {
        config()->set(
            'identity-access.account_lookup_keys.v1',
            (string) config('app.key'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('account lookup key must not equal APP_KEY');

        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_missing_current_key_fails_closed(): void
    {
        config()->set('people.identifier_lookup_keys', []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or unsafe versioned key');

        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_deterministic_adapter_is_rejected_outside_local_or_testing(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('restricted to local/testing');

        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }
}
