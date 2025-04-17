<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'items'])
            ->withCount('items')
            ->where('user_id', $request->user()->id);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('brand', 'ILIKE', "%{$search}%")
                    ->orWhere('model', 'ILIKE', "%{$search}%")
                    ->orWhere('spec', 'ILIKE', "%{$search}%");
            });
        }

        $products = $query
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'items' => $products->items(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'model' => 'nullable|string|max:255',
            'spec' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
        ]);

        $product = Product::create([
            ...$validated,
            'user_id' => $request->user()->id, // 如果有 user 綁定
            'uuid' => (string)Str::uuid(),
            'short_id' => Str::random(8),
        ]);

        return response()->json([
            'success' => true,
            'message' => '建立成功',
            'items' => [
                $product
            ],
        ]);
    }
}
