<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'location' => $this->faker->word(),
            'price' => $this->faker->randomFloat(2, 100, 10000),
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'uuid' => (string) Str::uuid(),
            'short_id' => Str::random(11),
        ];
    }
}
