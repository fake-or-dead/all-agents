<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Contracts\LocalVerificationMailbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LocalVerificationMailboxController extends Controller
{
    public function __construct(private readonly LocalVerificationMailbox $mailbox) {}

    public function recovery(Request $request): JsonResponse
    {
        if (
            ! app()->environment(['local', 'testing'])
            || config('identity-access.verification_adapter') !== 'deterministic-fake'
        ) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $path = $this->mailbox->latestRecoveryPathFor($validated['email']);

        if ($path === null) {
            throw new NotFoundHttpException;
        }

        return response()->json(['path' => $path]);
    }
}
