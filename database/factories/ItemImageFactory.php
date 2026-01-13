<?php

namespace Database\Factories;

use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ItemImage>
 */
class ItemImageFactory extends Factory
{
    protected $model = ItemImage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'image_path' => bin2hex(random_bytes(20)), // 40 字元隨機檔名
            'original_extension' => $this->faker->randomElement(['jpg', 'png', 'webp']),
            'status' => ItemImage::STATUS_DRAFT,
            'usage_count' => 0,
            'user_id' => User::factory(),
        ];
    }

    /**
     * 設定為已使用狀態
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ItemImage::STATUS_USED,
        ]);
    }

    /**
     * 設定為草稿狀態
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ItemImage::STATUS_DRAFT,
        ]);
    }
}
