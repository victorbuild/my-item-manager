<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryRepository
{
    /**
     * 取得用戶的所有分類（帶快取）
     *
     * @param int $userId
     * @return Collection
     */
    public function getAll(int $userId): Collection
    {
        $cacheKey = "categories:user:{$userId}";
        $cacheTTL = 3600; // 1 小時

        return Cache::remember($cacheKey, $cacheTTL, function () use ($userId) {
            return Category::where('user_id', $userId)
                ->select(['id', 'name', 'uuid'])
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * 清除用戶的分類快取
     *
     * @param int $userId
     * @return void
     */
    public function clearCache(int $userId): void
    {
        $cacheKey = "categories:user:{$userId}";
        Cache::forget($cacheKey);
    }

    public function create(array $data, int $userId): Category
    {
        $category = Category::create([
            'name' => $data['name'],
            'user_id' => $userId,
        ]);

        // 清除快取
        $this->clearCache($userId);

        return $category;
    }

    public function findOrFail(int $id, int $userId): Category
    {
        return Category::where('user_id', $userId)
            ->findOrFail($id);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        // 清除快取
        $this->clearCache($category->user_id);

        return $category->fresh();
    }

    public function delete(Category $category): bool
    {
        $userId = $category->user_id;
        $result = $category->delete();

        // 清除快取
        $this->clearCache($userId);

        return $result;
    }
}
