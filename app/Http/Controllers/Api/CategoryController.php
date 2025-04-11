<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CategoryController
{
    public function __construct(private readonly CategoryService $categoryService)
    {
    }

    public function index(): Collection
    {
        return $this->categoryService->getAll();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        $category = Category::create([
            'name' => $validated['name']
        ]);

        return response()->json($category, 201);
    }
}
