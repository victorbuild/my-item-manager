<?php

namespace Tests\Feature;

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
