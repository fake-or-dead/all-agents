<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Modules\IdentityAccess\Contracts\ActorResolver;
use App\Modules\IdentityAccess\Data\Actor;
use Illuminate\Http\Request;

final class LaravelAuthActorResolver implements ActorResolver
{
    public function resolve(Request $request): ?Actor
    {
        $account = $request->user();

        if ($account === null || ! $account->can('platform.probe')) {
            return null;
        }

        return new Actor('account', (string) $account->getAuthIdentifier(), ['platform.probe']);
    }
}
