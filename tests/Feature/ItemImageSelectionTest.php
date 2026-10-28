<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemImageSelectionTest extends TestCase
{
    use RefreshDatabase;

    /** 重複圖片應在建立批次前被拒絕，避免唯一索引錯誤及部分寫入。 */
    #[Test]
    public function it_should_reject_duplicate_images_without_creating_items_when_submitting_a_batch(): void
    {
        $user = User::factory()->create();
        $image = ItemImage::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/items', [
            'name' => '重複圖片測試',
            'quantity' => 2,
            'images' => [
                ['uuid' => $image->uuid, 'status' => 'new'],
                ['uuid' => strtoupper($image->uuid), 'status' => 'new'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['images.0.uuid', 'images.1.uuid']);

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('item_image_item', 0);
        $this->assertSame(0, $image->fresh()->usage_count);
    }

    /** 更新不得以重複圖片請求改寫欄位或圖片計數。 */
    #[Test]
    public function it_should_preserve_existing_data_when_update_contains_duplicate_images(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $user->id, 'name' => '原名稱']);
        $image = ItemImage::factory()->create([
            'user_id' => $user->id,
            'status' => ItemImage::STATUS_USED,
            'usage_count' => 1,
        ]);
        $item->images()->attach($image->uuid, ['sort_order' => 1]);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'name' => '不應儲存',
            'images' => [
                ['uuid' => $image->uuid, 'status' => 'original'],
                ['uuid' => $image->uuid, 'status' => 'new'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['images.0.uuid', 'images.1.uuid']);

        $this->assertSame('原名稱', $item->fresh()->name);
        $this->assertSame(1, $item->images()->count());
        $this->assertSame(1, $image->fresh()->usage_count);
    }

    /** 他人的圖片不得透過任何更新狀態存取，拒絕前不得改寫物品或計數。 */
    #[Test]
    public function it_should_reject_foreign_images_when_updating_an_item(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $user->id, 'name' => '原名稱']);
        $foreignImage = ItemImage::factory()->create(['usage_count' => 2, 'status' => ItemImage::STATUS_USED]);

        foreach (['new', 'original', 'removed'] as $status) {
            $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
                'name' => '不應儲存',
                'images' => [['uuid' => $foreignImage->uuid, 'status' => $status]],
            ])->assertUnprocessable()->assertJsonValidationErrors('images.0.uuid');
        }

        $this->assertSame('原名稱', $item->fresh()->name);
        $this->assertSame(0, $item->images()->count());
        $this->assertSame(2, $foreignImage->fresh()->usage_count);
        $this->assertSame(ItemImage::STATUS_USED, $foreignImage->fresh()->status);
    }

    /** 批次新增也不可繞過圖片擁有者驗證。 */
    #[Test]
    public function it_should_reject_foreign_images_without_creating_a_batch(): void
    {
        $user = User::factory()->create();
        $foreignImage = ItemImage::factory()->create();

        $this->actingAs($user)->postJson('/api/items', [
            'name' => '不應建立',
            'quantity' => 2,
            'images' => [['uuid' => $foreignImage->uuid, 'status' => 'new']],
        ])->assertUnprocessable()->assertJsonValidationErrors('images.0.uuid');

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('item_image_item', 0);
        $this->assertSame(0, $foreignImage->fresh()->usage_count);
    }

    /** 不存在的圖片應在任何欄位寫入前回傳驗證錯誤。 */
    #[Test]
    public function it_should_preserve_item_data_when_an_image_uuid_does_not_exist(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $user->id, 'name' => '原名稱']);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'name' => '不應儲存',
            'images' => [['uuid' => '00000000-0000-4000-8000-000000000001', 'status' => 'new']],
        ])->assertUnprocessable()->assertJsonValidationErrors('images.0.uuid');

        $this->assertSame('原名稱', $item->fresh()->name);
        $this->assertDatabaseCount('item_image_item', 0);
    }

    /** 仍允許編輯時加入自己已在其他物品使用的圖片。 */
    #[Test]
    public function it_should_attach_an_owned_shared_image_when_updating_an_item(): void
    {
        $user = User::factory()->create();
        $items = Item::factory()->count(2)->create(['user_id' => $user->id]);
        $image = ItemImage::factory()->create([
            'user_id' => $user->id,
            'usage_count' => 1,
            'status' => ItemImage::STATUS_USED,
        ]);
        $items[0]->images()->attach($image->uuid, ['sort_order' => 1]);

        $this->actingAs($user)->putJson("/api/items/{$items[1]->short_id}", [
            'images' => [['uuid' => $image->uuid, 'status' => 'new']],
        ])->assertSuccessful();

        $this->assertSame(2, $image->fresh()->usage_count);
        $this->assertSame(1, $items[1]->images()->count());
    }

    /** 同一張媒體圖片仍可正常關聯到同批建立的不同物品。 */
    #[Test]
    public function it_should_share_one_image_across_items_when_creating_a_valid_batch(): void
    {
        $user = User::factory()->create();
        $image = ItemImage::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/items', [
            'name' => '共用圖片測試',
            'quantity' => 2,
            'images' => [['uuid' => $image->uuid, 'status' => 'new']],
        ])->assertSuccessful();

        $this->assertDatabaseCount('items', 2);
        $this->assertDatabaseCount('item_image_item', 2);
        $this->assertSame(2, $image->fresh()->usage_count);
    }
}
