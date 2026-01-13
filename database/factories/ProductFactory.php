<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'brand' => $this->faker->company(),
            'model' => $this->faker->bothify('Model-###'),
            'spec' => $this->faker->sentence(),
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'short_id' => Str::random(11),
        ];
    }
}
