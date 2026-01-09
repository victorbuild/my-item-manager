<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

readonly class CategoryController
{
    public function __construct(private CategoryService $categoryService)
    {
    }

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        // 如果請求所有分類（用於下拉選單），不分頁
        if ($request->query('all') === 'true' || $request->query('all') === '1') {
            $categories = $this->categoryService->getAll($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => '取得成功',
                'items' => CategoryResource::collection($categories),
            ]);
        }

        // 否則使用分頁
        $perPage = (int)($request->query('per_page') ?? 10);
        $page = (int)($request->query('page') ?? 1);
        $search = $request->query('q');

        $result = $this->categoryService->getAllPaginated($request->user()->id, $page, $perPage, $search);

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'items' => CategoryResource::collection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = $this->categoryService->create($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => '成功建立分類',
            'items' => [new CategoryResource($category)],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateCategoryRequest $request, int $category): JsonResponse
    {
        $validated = $request->validated();
        $categoryModel = $this->categoryService->update($category, $validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'items' => [new CategoryResource($categoryModel)],
        ]);
    }

    public function destroy(\Illuminate\Http\Request $request, int $category): JsonResponse
    {
        try {
            $this->categoryService->delete($category, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => '分類已刪除',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(\Illuminate\Http\Request $request, int $category): JsonResponse
    {
        $perPage = (int)($request->query('per_page') ?? 10);
        $page = (int)($request->query('page') ?? 1);

        $result = $this->categoryService->getCategoryWithStats($category, $request->user()->id, $page, $perPage);

        return response()->json([
            'success' => true,
            'message' => '資料載入成功',
            'items' => [new CategoryResource($result['category'])],
            'stats' => $result['stats'],
            'products' => $result['products'],
            'meta' => $result['meta'],
        ]);
    }
}
