<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
     * 測試：更新物品 - 成功
     *
     * @test
     */
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
     * 測試：更新物品 - 驗證失敗（圖片數量超過限制）
     *
     * @test
     */
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
     *
     * @test
     */
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
     *
     * @test
     */
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
     *
     * @test
     */
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
     *
     * @test
     */
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
}
