<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Application\IdentityAccessWorkflow;
use App\Rules\MaxUtf8Bytes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class AccountSecurityController extends Controller
{
    public function __construct(private readonly IdentityAccessWorkflow $identityAccess) {}

    public function show(): Response
    {
        return Inertia::render('Auth/Account', ['csrfToken' => csrf_token()]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', new MaxUtf8Bytes(72)],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->numbers(),
                new MaxUtf8Bytes(72),
            ],
        ]);
        $result = $this->identityAccess->changePassword(
            (string) Auth::id(),
            $validated['current_password'],
            $validated['password'],
            (string) $request->session()->get('identity_access.auth_session_id', ''),
            (string) Str::uuid(),
        );

        if (! $result->successful) {
            return response()->json(['message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], 422);
        }

        return response()->json(['message' => 'เปลี่ยนรหัสผ่านแล้ว']);
    }
}
