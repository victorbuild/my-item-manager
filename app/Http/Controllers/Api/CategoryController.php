<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCategoryRequest;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

readonly class CategoryController
{
    public function __construct(private CategoryService $categoryService)
    {
    }

    public function index(): Collection
    {
        return $this->categoryService->getAll();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = $this->categoryService->create($validated);

        return response()->json($category, 201);
    }
}
