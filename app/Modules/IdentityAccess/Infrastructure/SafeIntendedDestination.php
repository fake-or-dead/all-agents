<?php

namespace App\Modules\IdentityAccess\Infrastructure;

final class SafeIntendedDestination
{
    public function accountPath(string $candidate): ?string
    {
        return $this->safePath($candidate, ['/account']);
    }

    public function memberPath(string $candidate): ?string
    {
        return $this->safePath(
            $candidate,
            ['/member', '/member/profile', '/member/applications', '/member/training', '/member/password'],
        );
    }

    /** @param list<string> $allowedPaths */
    private function safePath(string $candidate, array $allowedPaths): ?string
    {
        $decoded = rawurldecode($candidate);

        if (
            $candidate === ''
            || str_contains($candidate, '\\')
            || str_contains($decoded, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1
            || str_starts_with($candidate, '//')
        ) {
            return null;
        }

        $parts = parse_url($candidate);

        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || ! in_array(($parts['path'] ?? ''), $allowedPaths, true)
        ) {
            return null;
        }

        return $candidate;
    }
}
