<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryRepository
{
    public function getAll(int $userId): Collection
    {
        return Category::where('user_id', $userId)
            ->select(['id', 'name', 'uuid'])
            ->orderBy('name')
            ->get();
    }

    public function create(array $data, int $userId): Category
    {
        return Category::create([
            'name' => $data['name'],
            'user_id' => $userId,
        ]);
    }

    public function findOrFail(int $id, int $userId): Category
    {
        return Category::where('user_id', $userId)
            ->findOrFail($id);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(Category $category): bool
    {
        return $category->delete();
    }
}
