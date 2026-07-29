<?php

namespace App\Modules\ReferenceData\Infrastructure\Persistence;

use App\Modules\ReferenceData\Contracts\ReferenceData;
use App\Modules\ReferenceData\Data\ReferenceResult;
use Illuminate\Support\Facades\DB;

final class DatabaseReferenceData implements ReferenceData
{
    public function topLevel(string $type): ReferenceResult
    {
        if ($type !== 'province') {
            throw new \InvalidArgumentException('Unknown top-level reference type.');
        }

        $items = DB::table('provinces')
            ->where('active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'label' => (string) $row->name_th,
            ])->all();

        return new ReferenceResult(
            $items === [] ? 'empty' : 'ok',
            'province',
            null,
            $items,
        );
    }

    public function children(string $parentType, string $parentId): ReferenceResult
    {
        [$parentTable, $childTable, $foreignKey] = match ($parentType) {
            'province' => ['provinces', 'amphoes', 'province_id'],
            'amphoe' => ['amphoes', 'tambons', 'amphoe_id'],
            default => throw new \InvalidArgumentException('Unknown reference parent type.'),
        };

        $parentExists = DB::table($parentTable)
            ->where('id', $parentId)
            ->where('active', true)
            ->exists();

        if (! $parentExists) {
            return new ReferenceResult('unknown-parent', $parentType, $parentId, []);
        }

        $items = DB::table($childTable)
            ->where($foreignKey, $parentId)
            ->where('active', true)
            ->orderBy('display_order')
            ->orderBy('code')
            ->get()
            ->map(function (object $row) use ($parentType): array {
                $item = [
                    'id' => (string) $row->id,
                    'code' => (string) $row->code,
                    'label' => (string) $row->name_th,
                ];

                if ($parentType === 'amphoe') {
                    $item['postcode'] = (string) $row->postcode;
                }

                return $item;
            })->all();

        return new ReferenceResult(
            $items === [] ? 'empty' : 'ok',
            $parentType,
            $parentId,
            $items,
        );
    }
}
