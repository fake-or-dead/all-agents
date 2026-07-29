<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Modules\ApplicationWorkflow\Contracts\MemberApplicationHistory;
use App\Modules\IdentityAccess\Contracts\ApplicantOwnershipDirectory;
use App\Modules\People\Contracts\MemberProfileMutations;
use App\Modules\People\Contracts\MemberProfiles;
use App\Modules\People\Contracts\ProfileActivityRecorder;
use App\Modules\People\Data\AddressUpdate;
use App\Modules\People\Data\MutationResult;
use App\Modules\People\Data\ProfileUpdate;
use App\Modules\People\Data\TrainingUpdate;
use App\Modules\ReferenceData\Contracts\ReferenceData;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class MemberCenterController extends Controller
{
    public function __construct(
        private ApplicantOwnershipDirectory $ownership,
        private MemberProfiles $profiles,
        private MemberProfileMutations $mutations,
        private MemberApplicationHistory $applications,
        private ReferenceData $references,
        private ProfileActivityRecorder $activity,
    ) {}

    public function show(string $tab = 'profile'): Response
    {
        [$accountId, $personId] = $this->ownedIdentity();
        $profile = $this->profiles->profileFor($personId);
        abort_if($profile === null, 404);
        $this->activity->record(
            $accountId,
            $personId,
            'people.profile.viewed',
            'succeeded',
            (string) Str::uuid(),
            ['tab' => $tab],
        );

        return Inertia::render('Member/Center', [
            'activeTab' => $tab,
            'profile' => $profile->toArray(),
            'training' => $this->profiles->trainingFor($personId),
            'applications' => array_map(
                static fn ($application): array => $application->toArray(),
                $this->applications->forPerson($personId),
            ),
            'references' => [
                'provinces' => $this->references->topLevel('province')->items,
            ],
            'csrfToken' => csrf_token(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        [$accountId, $personId] = $this->ownedIdentity();
        $validated = $request->validate(
            [
                'given_name' => ['required', 'string', 'max:160'],
                'family_name' => ['required', 'string', 'max:160'],
                'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
                'phone' => ['nullable', 'string', 'regex:/^[0-9+() -]{8,24}$/'],
                'version' => ['required', 'integer', 'min:1'],
            ],
            $this->thaiValidationMessages(),
            $this->thaiValidationAttributes(),
        );
        $result = $this->mutations->updateProfile($accountId, new ProfileUpdate(
            $personId,
            trim($validated['given_name']),
            trim($validated['family_name']),
            $this->nullableTrim($validated['email'] ?? null),
            $this->nullableTrim($validated['phone'] ?? null),
            $validated['version'],
        ));

        return $this->mutationResponse($result, 'profile', 200);
    }

    public function updateAddress(Request $request): JsonResponse
    {
        [$accountId, $personId] = $this->ownedIdentity();
        $validated = $request->validate(
            [
                'address_line_1' => ['required', 'string', 'max:500'],
                'address_line_2' => ['nullable', 'string', 'max:500'],
                'province_id' => ['required', 'string', 'max:16'],
                'amphoe_id' => ['required', 'string', 'max:16'],
                'tambon_id' => ['required', 'string', 'max:16'],
                'version' => ['required', 'integer', 'min:0'],
            ],
            $this->thaiValidationMessages(),
            $this->thaiValidationAttributes(),
        );
        $result = $this->mutations->updateAddress($accountId, new AddressUpdate(
            $personId,
            trim($validated['address_line_1']),
            $this->nullableTrim($validated['address_line_2'] ?? null),
            $validated['province_id'],
            $validated['amphoe_id'],
            $validated['tambon_id'],
            $validated['version'],
        ));
        if ($result->code === 'invalid-reference') {
            return response()->json([
                'message' => 'ข้อมูลที่อยู่ไม่สัมพันธ์กัน',
                'code' => $result->code,
                'errors' => $result->value['errors'] ?? [],
                'input' => $request->only([
                    'address_line_1',
                    'address_line_2',
                    'province_id',
                    'amphoe_id',
                    'tambon_id',
                    'version',
                ]),
            ], 422);
        }

        return $this->mutationResponse($result, 'address', 200);
    }

    public function addTraining(Request $request): JsonResponse
    {
        [$accountId, $personId] = $this->ownedIdentity();
        $idempotencyKey = $request->header('Idempotency-Key');
        if (
            ! is_string($idempotencyKey)
            || preg_match('/\A[a-zA-Z0-9._:-]{8,128}\z/', $idempotencyKey) !== 1
        ) {
            return response()->json([
                'message' => 'กรุณาส่งคีย์คำขอที่ถูกต้อง',
                'errors' => [
                    'idempotency_key' => ['คีย์คำขอต้องมี 8–128 ตัวอักษรที่ระบบรองรับ'],
                ],
            ], 422);
        }
        $command = $this->trainingCommand($request, $personId, null);
        $result = $this->mutations->addTraining($accountId, $command, $idempotencyKey);

        return $this->mutationResponse($result, 'training', 201);
    }

    public function updateTraining(Request $request, string $trainingId): JsonResponse
    {
        [$accountId, $personId] = $this->ownedIdentity();
        $command = $this->trainingCommand($request, $personId, $trainingId);
        $result = $this->mutations->updateTraining($accountId, $command);

        return $this->mutationResponse($result, 'training', 200);
    }

    /** @return array{string, string} */
    private function ownedIdentity(): array
    {
        $accountId = Auth::id();
        abort_unless(is_string($accountId) && Str::isUuid($accountId), 403);
        $identity = $this->ownership->activeApplicantForAccount($accountId);
        abort_if($identity === null, 404);

        return [$accountId, $identity->personId];
    }

    private function trainingCommand(
        Request $request,
        string $personId,
        ?string $trainingId,
    ): TrainingUpdate {
        $rules = [
            'course_name' => ['required', 'string', 'max:300'],
            'provider_name' => ['required', 'string', 'max:300'],
            'started_on' => ['required', 'date_format:Y-m-d'],
            'ended_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:started_on'],
        ];
        if ($trainingId !== null) {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }
        $validated = $request->validate(
            $rules,
            $this->thaiValidationMessages(),
            $this->thaiValidationAttributes(),
        );

        return new TrainingUpdate(
            $personId,
            $trainingId,
            trim($validated['course_name']),
            trim($validated['provider_name']),
            CarbonImmutable::parse($validated['started_on']),
            isset($validated['ended_on']) && $validated['ended_on'] !== ''
                ? CarbonImmutable::parse($validated['ended_on'])
                : null,
            isset($validated['version']) ? (int) $validated['version'] : null,
        );
    }

    private function mutationResponse(
        MutationResult $result,
        string $key,
        int $successStatus,
    ): JsonResponse {
        if ($result->successful) {
            $response = [
                'message' => 'บันทึกข้อมูลแล้ว',
                $key => $result->value,
            ];
            if ($result->code === 'idempotent-replay') {
                $response['code'] = $result->code;
            }

            return response()->json(
                $response,
                $result->code === 'idempotent-replay' ? 200 : $successStatus,
            );
        }

        if ($result->code === 'idempotency-conflict') {
            return response()->json([
                'message' => 'คีย์คำขอนี้ถูกใช้กับข้อมูลอื่นแล้ว',
                'code' => $result->code,
            ], 409);
        }

        if ($result->code === 'stale') {
            return response()->json([
                'message' => 'ข้อมูลถูกแก้ไขจากอุปกรณ์อื่น กรุณาโหลดใหม่',
                'code' => 'stale',
            ], 409);
        }

        return response()->json([
            'message' => 'ไม่พบข้อมูลที่ได้รับอนุญาต',
            'code' => 'denied',
        ], 404);
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, string> */
    private function thaiValidationMessages(): array
    {
        return [
            'required' => 'กรุณากรอก :attribute',
            'string' => ':attribute ต้องเป็นข้อความ',
            'max' => ':attribute ยาวเกินกำหนด',
            'email' => 'รูปแบบ :attribute ไม่ถูกต้อง',
            'regex' => 'รูปแบบ :attribute ไม่ถูกต้อง',
            'date_format' => ':attribute ต้องเป็นวันที่รูปแบบ ปี-เดือน-วัน',
            'after_or_equal' => ':attribute ต้องไม่ก่อนวันที่เริ่ม',
            'integer' => ':attribute ไม่ถูกต้อง',
            'min' => ':attribute ไม่ถูกต้อง',
        ];
    }

    /** @return array<string, string> */
    private function thaiValidationAttributes(): array
    {
        return [
            'given_name' => 'ชื่อ',
            'family_name' => 'นามสกุล',
            'email' => 'อีเมลติดต่อ',
            'phone' => 'โทรศัพท์',
            'address_line_1' => 'ที่อยู่',
            'address_line_2' => 'รายละเอียดเพิ่มเติม',
            'province_id' => 'จังหวัด',
            'amphoe_id' => 'อำเภอ/เขต',
            'tambon_id' => 'ตำบล/แขวง',
            'course_name' => 'ชื่อหลักสูตร',
            'provider_name' => 'หน่วยงาน/ศูนย์',
            'started_on' => 'วันที่เริ่ม',
            'ended_on' => 'วันที่จบ',
            'version' => 'รุ่นข้อมูล',
        ];
    }
}
