<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryService
{
    public function getAll(): Collection
    {
        return Category::select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Category
    {
        return Category::create([
            'name' => $data['name']
        ]);
    }
}
