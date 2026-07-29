<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

final readonly class PrivacySafeRateLimiter
{
    public function __construct(
        private Repository $config,
        private PersonIdentityDirectory $people,
    ) {}

    /**
     * @param  array{client:int,identifier:int,pair:int,decay:int}  $limits
     */
    public function attemptEmail(
        string $action,
        string $clientAddress,
        string $email,
        array $limits,
        Closure $callback,
    ): bool {
        return $this->attempt(
            $action,
            $clientAddress,
            $this->pseudonym('email:'.mb_strtolower(trim($email))),
            $limits,
            $callback,
        );
    }

    /**
     * @param  array{client:int,identifier:int,pair:int,decay:int}  $limits
     */
    public function attemptIdentity(
        string $action,
        string $clientAddress,
        IdentityClaim $identity,
        array $limits,
        Closure $callback,
    ): bool {
        return $this->attempt(
            $action,
            $clientAddress,
            $this->people->rateLimitPseudonym($identity),
            $limits,
            $callback,
        );
    }

    public function clearIdentityFailures(
        string $action,
        string $clientAddress,
        IdentityClaim $identity,
    ): void {
        $identifier = $this->people->rateLimitPseudonym($identity);
        $client = $this->pseudonym("client:{$clientAddress}");

        RateLimiter::clear($this->key($action, 'identifier', $identifier));
        RateLimiter::clear($this->key($action, 'pair', "{$client}:{$identifier}"));
    }

    /**
     * @param  array{client:int,identifier:int,pair:int,decay:int}  $limits
     */
    private function attempt(
        string $action,
        string $clientAddress,
        string $identifier,
        array $limits,
        Closure $callback,
    ): bool {
        $client = $this->pseudonym("client:{$clientAddress}");
        $keys = [
            [$this->key($action, 'client', $client), $limits['client']],
            [$this->key($action, 'identifier', $identifier), $limits['identifier']],
            [$this->key($action, 'pair', "{$client}:{$identifier}"), $limits['pair']],
        ];

        foreach ($keys as [$key, $maximum]) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                return false;
            }
        }

        foreach ($keys as [$key]) {
            RateLimiter::hit($key, $limits['decay']);
        }

        $callback();

        return true;
    }

    private function key(string $action, string $scope, string $subject): string
    {
        return implode(':', [
            'identity-access',
            'rate',
            $this->keyVersion(),
            $action,
            $scope,
            $this->pseudonym("{$scope}:{$subject}"),
        ]);
    }

    private function pseudonym(string $value): string
    {
        return hash_hmac('sha256', $value, $this->keyMaterial());
    }

    private function keyVersion(): string
    {
        return (string) $this->config->get('identity-access.rate_limit_key_version');
    }

    private function keyMaterial(): string
    {
        $version = $this->keyVersion();
        $keys = $this->config->get('identity-access.rate_limit_keys');
        $key = is_array($keys) ? ($keys[$version] ?? null) : null;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Missing rate-limit pseudonym key.');
        }

        return $key;
    }
}
