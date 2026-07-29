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

    public function test_previous_lookup_key_is_checked_for_strength_and_app_key_reuse(): void
    {
        config()->set('people.identifier_lookup_keys.v0', 'too-short');

        try {
            $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
            self::fail('Unsafe previous key was accepted.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Missing or unsafe versioned key', $exception->getMessage());
        }

        config()->set('people.identifier_lookup_keys.v0', (string) config('app.key'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('people lookup key must not equal APP_KEY');
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_previous_key_must_not_be_reused_by_another_security_domain(): void
    {
        config()->set(
            'identity-access.account_lookup_keys.v0',
            (string) config('people.identifier_lookup_keys.v1'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Identity security keys must be distinct');
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_people_lookup_rejects_equal_current_and_previous_versions_with_different_keys(): void
    {
        config()->set('people.identifier_lookup_previous_version', 'v1');
        config()->set('people.identifier_lookup_previous_key', str_repeat('p', 32));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('people lookup current and previous key versions must differ');
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_account_lookup_rejects_equal_current_and_previous_versions_with_the_same_key(): void
    {
        $key = (string) config('identity-access.account_lookup_keys.v1');
        config()->set('identity-access.account_lookup_previous_version', 'v1');
        config()->set('identity-access.account_lookup_previous_key', $key);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('account lookup current and previous key versions must differ');
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_people_lookup_rejects_a_previous_version_without_its_key(): void
    {
        config()->set('people.identifier_lookup_previous_version', 'v0');
        config()->set('people.identifier_lookup_previous_key', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('people lookup previous version and key must be configured together');
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_account_lookup_rejects_a_previous_key_without_its_version(): void
    {
        config()->set('identity-access.account_lookup_previous_version', '');
        config()->set('identity-access.account_lookup_previous_key', str_repeat('a', 32));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('account lookup previous version and key must be configured together');
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_previous_key_must_be_mapped_under_its_declared_version(): void
    {
        config()->set('people.identifier_lookup_previous_version', 'v0');
        config()->set('people.identifier_lookup_previous_key', str_repeat('p', 32));
        config()->set('people.identifier_lookup_keys.v0', str_repeat('q', 32));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('people lookup previous key must be mapped under version v0');
        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_canonical_local_environment_exposes_both_previous_rotation_pairs(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));
        $compose = (string) file_get_contents(base_path('compose.yaml'));
        $bootstrap = (string) file_get_contents(base_path('bin/bootstrap-env'));
        $names = [
            'PEOPLE_IDENTIFIER_LOOKUP_PREVIOUS_VERSION',
            'PEOPLE_IDENTIFIER_LOOKUP_PREVIOUS_KEY',
            'IDENTITY_ACCOUNT_LOOKUP_PREVIOUS_VERSION',
            'IDENTITY_ACCOUNT_LOOKUP_PREVIOUS_KEY',
        ];

        foreach ($names as $name) {
            self::assertMatchesRegularExpression(
                "/^{$name}=$/m",
                $example,
                "{$name} must be declared empty in .env.example.",
            );
            self::assertStringContainsString(
                "{$name}: \${{$name}:-}",
                $compose,
                "{$name} must be propagated to every canonical Compose app service.",
            );
            self::assertStringContainsString(
                "ensure_present {$name}",
                $bootstrap,
                "{$name} must be appended without generating or replacing rotation secrets.",
            );
        }
    }

    public function test_boot_validation_rejects_an_unreviewed_bcrypt_round_before_serving_requests(): void
    {
        config()->set('hashing.bcrypt.rounds', 11);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported configured bcrypt cost 11');

        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }

    public function test_boot_validation_rejects_a_dummy_hash_with_the_wrong_declared_cost(): void
    {
        config()->set(
            'identity-access.bcrypt_dummy_hashes.10',
            (string) config('identity-access.bcrypt_dummy_hashes.12'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid dummy bcrypt hash for cost 10');

        $this->app->make(IdentitySecurityConfiguration::class)->assertSafe();
    }
}
