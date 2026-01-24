<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * 測試：成功建立產品
     */
    #[Test]
    public function it_should_create_product_successfully(): void
    {
        // Arrange
        $payload = [
            'name' => '測試產品',
            'brand' => '測試品牌',
            'model' => '測試型號',
            'spec' => '測試規格',
            'barcode' => '1234567890',
            'category_id' => null,
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/products', $payload);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '成功建立產品',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'uuid',
                    'short_id',
                    'user_id',
                    'category_id',
                    'name',
                    'brand',
                    'model',
                    'spec',
                    'barcode',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => '測試產品',
            'user_id' => $this->user->id,
        ]);

        $this->assertNotNull(Product::first()?->uuid);
        $this->assertNotNull(Product::first()?->short_id);
    }

    /**
     * 測試：驗證失敗時返回 422
     */
    #[Test]
    public function it_should_return_422_when_validation_fails(): void
    {
        // Arrange
        $payload = [];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/products', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * 測試：category_id 只能使用自己的分類
     */
    #[Test]
    public function it_should_return_422_when_category_id_is_not_owned_by_user(): void
    {
        // Arrange
        $otherUser = User::factory()->create();
        $otherCategory = Category::create([
            'name' => '其他人的分類',
            'user_id' => $otherUser->id,
        ]);

        $payload = [
            'name' => '測試產品',
            'category_id' => $otherCategory->id,
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/products', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['category_id']);
    }
}
