<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Support\Collection;

readonly class CategoryService
{
    public function __construct(private CategoryRepository $categoryRepository)
    {
    }

    public function getAll(): Collection
    {
        return $this->categoryRepository->getAll();
    }

    public function create(array $data): Category
    {
        return $this->categoryRepository->create($data);
    }
}
