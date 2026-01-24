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

    /**
     * 測試：更新產品 - 成功
     */
    #[Test]
    public function it_should_update_product_successfully(): void
    {
        // Arrange
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'name' => '原始產品',
            'short_id' => 'prd-001',
        ]);

        $payload = [
            'name' => '更新後的產品',
            'brand' => '新品牌',
            'category_id' => null,
            'model' => '新型號',
            'spec' => '新規格',
            'barcode' => '9876543210',
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->putJson("/api/products/{$product->short_id}", $payload);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '更新成功',
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
            'id' => $product->id,
            'name' => '更新後的產品',
        ]);
    }

    /**
     * 測試：更新他人的產品 - 403（ProductPolicy update）
     */
    #[Test]
    public function it_should_return_403_when_updating_other_users_product(): void
    {
        // Arrange
        $userB = User::factory()->create();
        $productB = Product::factory()->create([
            'user_id' => $userB->id,
            'short_id' => 'prd-b-001',
        ]);

        $payload = [
            'name' => '嘗試更新',
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->putJson("/api/products/{$productB->short_id}", $payload);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * 測試：未登入更新產品 - 401
     */
    #[Test]
    public function it_should_return_401_when_updating_product_unauthenticated(): void
    {
        // Arrange
        $product = Product::factory()->create([
            'short_id' => 'prd-unauth-001',
        ]);

        $payload = [
            'name' => '嘗試更新（未登入）',
        ];

        // Act
        $response = $this->putJson("/api/products/{$product->short_id}", $payload);

        // Assert
        $response->assertStatus(401);
    }

    /**
     * 測試：更新產品時 category_id 只能使用自己的分類
     */
    #[Test]
    public function it_should_return_422_when_updating_product_with_not_owned_category_id(): void
    {
        // Arrange
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'short_id' => 'prd-002',
        ]);

        $otherUser = User::factory()->create();
        $otherCategory = Category::create([
            'name' => '其他人的分類（更新用）',
            'user_id' => $otherUser->id,
        ]);

        $payload = [
            'name' => '更新後的產品',
            'category_id' => $otherCategory->id,
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->putJson("/api/products/{$product->short_id}", $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['category_id']);
    }

    /**
     * 測試：更新產品時驗證失敗 - 422
     */
    #[Test]
    public function it_should_return_422_when_updating_product_validation_fails(): void
    {
        // Arrange
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'short_id' => 'prd-003',
        ]);

        $payload = [];

        // Act
        $response = $this->actingAs($this->user)
            ->putJson("/api/products/{$product->short_id}", $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['name']);
    }
}
