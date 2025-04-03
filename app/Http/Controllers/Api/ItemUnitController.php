<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $itemUnit = ItemUnit::findOrFail($id);

        if ($request->isMethod('put')) {
            // TODO: 若未來需要支援整體取代，可在此處處理
            return response()->json(['message' => 'PUT 尚未實作'], 501);
        }

        if ($request->isMethod('patch')) {
            $validated = $request->validate([
                'used_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
                'discarded_at' => [
                    'sometimes',
                    'nullable',
                    'date',
                    'before_or_equal:today',
                    function ($attribute, $value, $fail) use ($request, $itemUnit) {
                        if ($value) {
                            // 優先用送進來的 used_at，否則抓資料庫裡原本的
                            $usedAt = $request->input('used_at') ?? $itemUnit->used_at;
                            if ($usedAt && $value < $usedAt) {
                                $fail('報廢日期不能早於使用日期。');
                            }
                        }
                    },
                ],
            ]);

            $itemUnit->fill($validated)->save();

            if (array_key_exists('discarded_at', $validated)) {
                $item = $itemUnit->item;

                $allDiscarded = $item->units()->whereNull('discarded_at')->count() === 0;

                if ($allDiscarded) {
                    // 全部報廢 → 更新 item 為報廢
                    $item->discarded_at = now();
                    $item->is_discarded = true;
                    $item->save();
                } else {
                    // 有人取消報廢 → 取消 item 的報廢狀態
                    $item->discarded_at = null;
                    $item->is_discarded = false;
                    $item->save();
                }
            }
        }

        return response()->json([
            'message' => '更新成功',
            'data' => $itemUnit
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
