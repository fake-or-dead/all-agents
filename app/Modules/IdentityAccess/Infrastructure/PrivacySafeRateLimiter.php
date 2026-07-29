<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Data\IdentityClaim;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Cache;
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

        // Redis Cache::lock is an atomic distributed mutex. All three bucket
        // checks and increments therefore observe one linearized request,
        // rather than three stale reads followed by three independent writes.
        // This is deliberately action-scoped, not pair-scoped: concurrent
        // requests with a different identifier still share the client bucket,
        // and requests from a different client still share the identifier
        // bucket. One Redis mutex gives every overlapping bucket set a single
        // serialization point without putting any PII in a key.
        $lock = Cache::lock(
            'identity-access:rate-lock:'.$this->keyVersion().':'.$action,
            max(2, $limits['decay']),
        );

        try {
            return $lock->block(2, function () use ($keys, $limits, $callback): bool {
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
            });
        } catch (LockTimeoutException) {
            // A contended abuse-control request fails closed and keeps the
            // public response neutral in its controller.
            return false;
        }
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
