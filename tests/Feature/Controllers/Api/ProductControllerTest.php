<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Category;
use App\Models\Item;
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
     * 測試：未登入取得產品列表 - 401
     */
    #[Test]
    public function it_should_return_401_when_listing_products_unauthenticated(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    /**
     * 測試：取得產品列表 - 成功（含 meta / data）
     */
    #[Test]
    public function it_should_list_products_successfully(): void
    {
        // Arrange
        Product::factory()->create([
            'user_id' => $this->user->id,
            'name' => '我的產品 A',
            'short_id' => 'prd-list-a',
        ]);
        Product::factory()->create([
            'user_id' => $this->user->id,
            'name' => '我的產品 B',
            'short_id' => 'prd-list-b',
        ]);

        // Act
        $response = $this->actingAs($this->user)->getJson('/api/products');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '取得成功',
            ])
            ->assertJsonStructure([
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'data' => [
                    '*' => [
                        'id',
                        'short_id',
                        'name',
                        'brand',
                        'category',
                        'owned_items_count',
                        'latest_owned_item',
                    ],
                ],
            ]);
    }

    /**
     * 測試：產品列表 q 搜尋可過濾結果
     */
    #[Test]
    public function it_should_filter_products_by_search_query(): void
    {
        // Arrange
        Product::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'iPhone 15',
            'short_id' => 'prd-search-1',
        ]);
        Product::factory()->create([
            'user_id' => $this->user->id,
            'name' => '其他產品',
            'short_id' => 'prd-search-2',
        ]);

        // Act
        $response = $this->actingAs($this->user)->getJson('/api/products?q=iPhone');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'iPhone 15');
    }

    /**
     * 測試：未登入取得產品詳情 - 401
     */
    #[Test]
    public function it_should_return_401_when_viewing_product_unauthenticated(): void
    {
        $product = Product::factory()->create([
            'short_id' => 'prd-show-unauth-001',
        ]);

        $response = $this->getJson("/api/products/{$product->short_id}");

        $response->assertStatus(401);
    }

    /**
     * 測試：取得產品詳情 - 成功（含 stats）
     */
    #[Test]
    public function it_should_show_product_successfully_with_stats(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'short_id' => 'prd-show-001',
            'name' => '測試產品詳情',
        ]);

        // pre_arrival（未到貨）
        Item::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'received_at' => null,
            'used_at' => null,
            'discarded_at' => null,
        ]);

        // unused（已到貨未使用）
        Item::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'received_at' => now()->subDays(3),
            'used_at' => null,
            'discarded_at' => null,
        ]);

        // in_use（使用中）
        Item::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'received_at' => now()->subDays(10),
            'used_at' => now()->subDays(2),
            'discarded_at' => null,
        ]);

        // unused_discarded（未使用就棄用）
        Item::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'received_at' => now()->subDays(10),
            'used_at' => null,
            'discarded_at' => now()->subDays(1),
        ]);

        // used_discarded（使用後棄用）
        Item::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'received_at' => now()->subDays(20),
            'used_at' => now()->subDays(15),
            'discarded_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/products/{$product->short_id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '取得成功',
            ])
            ->assertJsonPath('data.short_id', $product->short_id)
            ->assertJsonPath('data.name', '測試產品詳情')
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
                    'category',
                    'created_at',
                    'updated_at',
                    'stats' => [
                        'pre_arrival',
                        'unused',
                        'in_use',
                        'unused_discarded',
                        'used_discarded',
                    ],
                ],
            ])
            ->assertJsonPath('data.stats.pre_arrival', 1)
            ->assertJsonPath('data.stats.unused', 1)
            ->assertJsonPath('data.stats.in_use', 1)
            ->assertJsonPath('data.stats.unused_discarded', 1)
            ->assertJsonPath('data.stats.used_discarded', 1);
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

    /**
     * 測試：未登入刪除產品 - 401
     */
    #[Test]
    public function it_should_return_401_when_deleting_product_unauthenticated(): void
    {
        $product = Product::factory()->create([
            'short_id' => 'prd-del-unauth-001',
        ]);

        $response = $this->deleteJson("/api/products/{$product->short_id}");

        $response->assertStatus(401);
    }

    /**
     * 測試：刪除他人的產品 - 403（ProductPolicy delete）
     */
    #[Test]
    public function it_should_return_403_when_deleting_other_users_product(): void
    {
        $userB = User::factory()->create();
        $productB = Product::factory()->create([
            'user_id' => $userB->id,
            'short_id' => 'prd-del-b-001',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/products/{$productB->short_id}");

        $response->assertStatus(403);
    }

    /**
     * 測試：刪除自己的產品 - 成功（204）
     */
    #[Test]
    public function it_should_delete_product_successfully(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'short_id' => 'prd-del-001',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/products/{$product->short_id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    /**
     * 測試：產品仍有關聯物品時不可刪除 - 422
     */
    #[Test]
    public function it_should_return_422_when_deleting_product_with_items(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'short_id' => 'prd-del-002',
        ]);

        Item::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/products/{$product->short_id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => '此產品仍有關聯物品，無法刪除',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }
}
