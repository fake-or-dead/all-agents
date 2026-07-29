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
                'string',
                Password::min(12)->letters()->numbers(),
                new MaxUtf8Bytes(72),
            ],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ], [
            'current_password.required' => 'กรุณาระบุรหัสผ่านปัจจุบัน',
            'current_password.string' => 'รหัสผ่านปัจจุบันต้องเป็นข้อความ',
            'password.required' => 'กรุณาระบุรหัสผ่านใหม่',
            'password.string' => 'รหัสผ่านใหม่ต้องเป็นข้อความ',
            'password.min' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 12 ตัวอักษร',
            'password.letters' => 'รหัสผ่านใหม่ต้องมีตัวอักษรอย่างน้อย 1 ตัว',
            'password.numbers' => 'รหัสผ่านใหม่ต้องมีตัวเลขอย่างน้อย 1 ตัว',
            'password_confirmation.required' => 'กรุณายืนยันรหัสผ่านใหม่',
            'password_confirmation.string' => 'คำยืนยันรหัสผ่านใหม่ต้องเป็นข้อความ',
            'password_confirmation.same' => 'คำยืนยันรหัสผ่านใหม่ไม่ตรงกับรหัสผ่านใหม่',
        ]);
        $result = $this->identityAccess->changePassword(
            (string) Auth::id(),
            $validated['current_password'],
            $validated['password'],
            (string) $request->session()->get('identity_access.auth_session_id', ''),
            (string) Str::uuid(),
        );

        if (! $result->successful) {
            $message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';

            return response()->json([
                'message' => $message,
                'errors' => ['current_password' => [$message]],
            ], 422);
        }

        return response()->json(['message' => 'เปลี่ยนรหัสผ่านแล้ว']);
    }
}
