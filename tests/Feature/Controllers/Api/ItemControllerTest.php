<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemControllerTest extends TestCase
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
     * 測試：index 只回傳當前登入使用者的物品，不包含其他使用者的
     */
    #[Test]
    public function it_should_only_return_current_users_items_in_index(): void
    {
        // Arrange
        $userA = $this->user;
        $userB = User::factory()->create();

        $itemsA = Item::factory()->count(2)->create(['user_id' => $userA->id]);
        $itemsB = Item::factory()->count(3)->create(['user_id' => $userB->id]);

        $expectedShortIds = $itemsA->pluck('short_id')->sort()->values()->toArray();
        $shortIdsB = $itemsB->pluck('short_id')->toArray();

        // Act
        $response = $this->actingAs($userA)->getJson('/api/items');

        // Assert
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => '取得成功']);

        $items = $response->json('data');
        $this->assertCount(2, $items);

        $actualShortIds = collect($items)->pluck('short_id')->sort()->values()->toArray();

        // 可以看到自己的：回應的 2 筆就是 A 的
        $this->assertEquals($expectedShortIds, $actualShortIds);

        // 看不到別人的：回應中不包含任何 B 的 short_id
        $this->assertEmpty(
            array_intersect($actualShortIds, $shortIdsB),
            'index 回應不應包含他人（userB）的物品'
        );
    }

    /**
     * 測試：index 可使用 product_short_id 篩選
     */
    #[Test]
    public function it_should_filter_items_by_product_short_id(): void
    {
        $productA = Product::factory()->create([
            'user_id' => $this->user->id,
            'short_id' => 'prd-filter-a',
        ]);
        $productB = Product::factory()->create([
            'user_id' => $this->user->id,
            'short_id' => 'prd-filter-b',
        ]);

        $itemsA = Item::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'product_id' => $productA->id,
        ]);
        Item::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'product_id' => $productB->id,
        ]);

        $expectedShortIds = $itemsA->pluck('short_id')->sort()->values()->toArray();

        $response = $this->actingAs($this->user)->getJson('/api/items?product_short_id=prd-filter-a');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => '取得成功']);

        $items = $response->json('data');
        $this->assertCount(2, $items);

        $actualShortIds = collect($items)->pluck('short_id')->sort()->values()->toArray();
        $this->assertEquals($expectedShortIds, $actualShortIds);
    }

    /**
     * 測試：show 自己的物品 - 成功
     */
    #[Test]
    public function it_should_return_200_when_viewing_own_item(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id, 'name' => '我的物品']);

        $response = $this->actingAs($this->user)->getJson("/api/items/{$item->short_id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => '資料載入成功'])
            ->assertJsonPath('data.name', '我的物品');
    }

    /**
     * 測試：show 他人的物品 - 403（ItemPolicy view）
     */
    #[Test]
    public function it_should_return_403_when_viewing_other_users_item(): void
    {
        $userB = User::factory()->create();
        $itemB = Item::factory()->create(['user_id' => $userB->id, 'name' => 'B 的物品']);

        $response = $this->actingAs($this->user)->getJson("/api/items/{$itemB->short_id}");

        $response->assertStatus(403);
    }

    /**
     * 測試：更新物品 - 成功
     */
    #[Test]
    public function it_should_update_item_successfully(): void
    {
        // Arrange
        $this->actingAs($this->user);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
            'name' => '原始名稱',
            'price' => 1000,
        ]);

        $updateData = [
            'name' => '更新後的名稱',
            'price' => 2000,
        ];

        // Act
        $response = $this->putJson("/api/items/{$item->short_id}", $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '更新成功',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'price',
                ],
            ]);

        $item->refresh();
        $this->assertEquals('更新後的名稱', $item->name);
        $this->assertEquals(2000, $item->price);
    }

    /**
     * 測試：更新他人的物品 - 403（ItemPolicy update）
     */
    #[Test]
    public function it_should_return_403_when_updating_other_users_item(): void
    {
        $userB = User::factory()->create();
        $itemB = Item::factory()->create(['user_id' => $userB->id, 'name' => 'B 的物品']);

        $response = $this->actingAs($this->user)->putJson("/api/items/{$itemB->short_id}", [
            'name' => '想改成我的',
        ]);

        $response->assertStatus(403);
        $itemB->refresh();
        $this->assertSame('B 的物品', $itemB->name);
    }

    /**
     * 測試：更新物品 - 驗證失敗（圖片數量超過限制）
     */
    #[Test]
    public function it_should_return_422_when_image_count_exceeds_limit(): void
    {
        // Arrange
        $this->actingAs($this->user);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // 建立真實的 ItemImage 物件以取得有效的 UUID
        $images = [];
        for ($i = 1; $i <= 10; $i++) {
            $image = \App\Models\ItemImage::factory()->create();
            $images[] = [
                'uuid' => $image->uuid,
                'status' => 'new',
            ];
        }

        $updateData = [
            'name' => '更新後的名稱',
            'images' => $images,
        ];

        // Act
        $response = $this->putJson("/api/items/{$item->short_id}", $updateData);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => '最多只能有 9 張圖片',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'images',
                ],
            ]);
    }

    /**
     * 測試：更新物品 - 同步圖片（新增）
     */
    #[Test]
    public function it_should_sync_images_when_updating_item(): void
    {
        // Arrange
        $this->actingAs($this->user);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $image1 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);
        $image2 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);

        $updateData = [
            'name' => '更新後的名稱',
            'images' => [
                ['uuid' => $image1->uuid, 'status' => 'new'],
                ['uuid' => $image2->uuid, 'status' => 'new'],
            ],
        ];

        // Act
        $response = $this->putJson("/api/items/{$item->short_id}", $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '更新成功',
            ]);

        $item->refresh();
        $this->assertEquals(2, $item->images()->count());
        $this->assertTrue($item->images->contains('uuid', $image1->uuid));
        $this->assertTrue($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：更新物品 - 同步圖片（移除）
     */
    #[Test]
    public function it_should_remove_images_when_updating_item(): void
    {
        // Arrange
        $this->actingAs($this->user);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $image1 = ItemImage::factory()->create(['usage_count' => 1]);
        $image2 = ItemImage::factory()->create(['usage_count' => 1]);

        // 先附加圖片
        $item->images()->attach($image1->uuid, ['sort_order' => 1]);
        $item->images()->attach($image2->uuid, ['sort_order' => 2]);

        $updateData = [
            'name' => '更新後的名稱',
            'images' => [
                ['uuid' => $image1->uuid, 'status' => 'removed'],
                ['uuid' => $image2->uuid, 'status' => 'original'], // 保留
            ],
        ];

        // Act
        $response = $this->putJson("/api/items/{$item->short_id}", $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '更新成功',
            ]);

        $item->refresh();
        $this->assertEquals(1, $item->images()->count()); // 只剩 image2
        $this->assertFalse($item->images->contains('uuid', $image1->uuid));
        $this->assertTrue($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：更新物品 - 未認證
     */
    #[Test]
    public function it_should_return_401_when_unauthenticated(): void
    {
        // Arrange
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $updateData = [
            'name' => '更新後的名稱',
        ];

        // Act
        $response = $this->putJson("/api/items/{$item->short_id}", $updateData);

        // Assert
        $response->assertStatus(401);
    }

    /**
     * 測試：更新物品 - 驗證失敗（表單驗證）
     */
    #[Test]
    public function it_should_return_422_when_validation_fails(): void
    {
        // Arrange
        $this->actingAs($this->user);
        $item = Item::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $updateData = [
            'name' => '', // 空字串，應該驗證失敗
        ];

        // Act
        $response = $this->putJson("/api/items/{$item->short_id}", $updateData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    /**
     * 測試：delete 自己的物品 - 204
     */
    #[Test]
    public function it_should_return_204_when_deleting_own_item(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/items/{$item->short_id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    /**
     * 測試：delete 他人的物品 - 403（ItemPolicy delete）
     */
    #[Test]
    public function it_should_return_403_when_deleting_other_users_item(): void
    {
        $userB = User::factory()->create();
        $itemB = Item::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/items/{$itemB->short_id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('items', ['id' => $itemB->id]);
    }

    /**
     * 測試：expiringSoon 端點 - 成功回傳資料
     */
    #[Test]
    public function it_should_return_expiring_soon_items_successfully(): void
    {
        // Arrange
        $item1 = Item::factory()->create([
            'user_id' => $this->user->id,
            'expiration_date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $this->user->id,
            'expiration_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        // Act
        $response = $this->actingAs($this->user)->getJson('/api/items/expiring-soon', [
            'days' => 30,
            'per_page' => 20,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '取得成功',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
                'data',
                'range_statistics',
                'total_all_with_expiration_date',
            ]);

        // 驗證使用 data 而非 items
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayNotHasKey('items', $response->json());
    }

    /**
     * 測試：expiringSoon 端點 - Form Request 驗證失敗（days 超出範圍）
     */
    #[Test]
    public function it_should_return_422_when_days_exceeds_maximum(): void
    {
        // Act
        $response = $this->actingAs($this->user)->getJson('/api/items/expiring-soon?days=2000&per_page=20');

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'days',
                ],
            ]);
    }

    /**
     * 測試：expiringSoon 端點 - Form Request 驗證失敗（per_page 超出範圍）
     */
    #[Test]
    public function it_should_return_422_when_per_page_exceeds_maximum(): void
    {
        // Act
        $response = $this->actingAs($this->user)->getJson('/api/items/expiring-soon?days=30&per_page=200');

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'per_page',
                ],
            ]);
    }

    /**
     * 測試：expiringSoon 端點 - 使用預設值
     */
    #[Test]
    public function it_should_use_default_values_when_params_not_provided(): void
    {
        // Act
        $response = $this->actingAs($this->user)->getJson('/api/items/expiring-soon');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '取得成功',
            ]);

        // 驗證預設值（days=30, per_page=20）
        $meta = $response->json('meta');
        $this->assertEquals(20, $meta['per_page']);
    }

    /**
     * 測試：expiringSoon 端點 - 未認證
     */
    #[Test]
    public function it_should_return_401_when_unauthenticated_for_expiring_soon(): void
    {
        // Act
        $response = $this->getJson('/api/items/expiring-soon');

        // Assert
        $response->assertStatus(401);
    }
}
