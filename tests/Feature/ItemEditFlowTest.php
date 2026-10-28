<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemEditFlowTest extends TestCase
{
    use RefreshDatabase;

    /** 單件編輯保留零元成本，並依畫面順序更新圖片而不建立額外物品。 */
    #[Test]
    public function it_should_preserve_zero_cost_and_apply_photo_order_when_updating_one_item(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $user->id, 'price' => 15, 'notes' => '原備註']);
        $first = ItemImage::factory()->create([
            'user_id' => $user->id,
            'status' => ItemImage::STATUS_USED,
            'usage_count' => 1,
        ]);
        $second = ItemImage::factory()->create([
            'user_id' => $user->id,
            'status' => ItemImage::STATUS_USED,
            'usage_count' => 1,
        ]);
        $new = ItemImage::factory()->create(['user_id' => $user->id]);
        $item->images()->attach($first->uuid, ['sort_order' => 1]);
        $item->images()->attach($second->uuid, ['sort_order' => 2]);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'name' => '編輯後',
            'price' => 0,
            'images' => [
                ['uuid' => $second->uuid, 'status' => 'original'],
                ['uuid' => $new->uuid, 'status' => 'new'],
                ['uuid' => $first->uuid, 'status' => 'removed'],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseCount('items', 1);
        $this->assertSame(0.0, (float) $item->fresh()->price);
        $this->assertSame('原備註', $item->fresh()->notes);
        $this->assertDatabaseHas('item_image_item', [
            'item_id' => $item->id, 'item_image_uuid' => $second->uuid, 'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('item_image_item', [
            'item_id' => $item->id, 'item_image_uuid' => $new->uuid, 'sort_order' => 2,
        ]);
        $this->assertDatabaseMissing('item_image_item', [
            'item_id' => $item->id, 'item_image_uuid' => $first->uuid,
        ]);
        $this->assertSame(1, $new->fresh()->usage_count);
    }

    /** 移除全部照片僅解除目前物品的關聯，不影響其他物品共用的媒體。 */
    #[Test]
    public function it_should_keep_shared_media_when_all_photos_are_removed_from_one_item(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create(['user_id' => $user->id]);
        $other = Item::factory()->create(['user_id' => $user->id]);
        $image = ItemImage::factory()->create([
            'user_id' => $user->id,
            'status' => ItemImage::STATUS_USED,
            'usage_count' => 2,
        ]);
        $item->images()->attach($image->uuid, ['sort_order' => 1]);
        $other->images()->attach($image->uuid, ['sort_order' => 1]);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'images' => [['uuid' => $image->uuid, 'status' => 'removed']],
        ])->assertSuccessful();

        $this->assertSame(0, $item->images()->count());
        $this->assertSame(1, $other->images()->count());
        $this->assertSame(1, $image->fresh()->usage_count);
        $this->assertDatabaseHas('item_images', ['uuid' => $image->uuid]);
    }
}
