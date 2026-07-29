<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\ReferenceData\Contracts\ReferenceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReferenceDataController extends Controller
{
    public function provinces(ReferenceData $references): JsonResponse
    {
        $result = $references->topLevel('province');

        return response()->json([
            'data' => $result->items,
            'meta' => [
                'status' => $result->status,
                'parent_type' => null,
                'parent_id' => null,
                'type' => 'province',
            ],
            'errors' => [],
        ]);
    }

    public function amphoes(Request $request, ReferenceData $references): JsonResponse
    {
        return $this->response($request->query('province_id'), 'province', $references);
    }

    public function tambons(Request $request, ReferenceData $references): JsonResponse
    {
        return $this->response($request->query('amphoe_id'), 'amphoe', $references);
    }

    private function response(
        mixed $parentId,
        string $parentType,
        ReferenceData $references,
    ): JsonResponse {
        if ($parentId === null || $parentId === '') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'status' => 'missing-parent',
                    'parent_type' => $parentType,
                    'parent_id' => null,
                ],
                'errors' => ['parent_id' => ['ต้องระบุรหัสข้อมูลแม่']],
            ], 422);
        }

        if (! is_string($parentId) || preg_match('/^[a-z0-9-]{1,16}$/', $parentId) !== 1) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'status' => 'malformed-parent',
                    'parent_type' => $parentType,
                    'parent_id' => null,
                ],
                'errors' => ['parent_id' => ['รูปแบบรหัสข้อมูลแม่ไม่ถูกต้อง']],
            ], 422);
        }

        $result = $references->children($parentType, $parentId);

        return response()->json([
            'data' => $result->items,
            'meta' => [
                'status' => $result->status,
                'parent_type' => $result->parentType,
                'parent_id' => $result->parentId,
            ],
            'errors' => [],
        ]);
    }
}
