<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemProductRelinkTest extends TestCase
{
    use RefreshDatabase;

    /** 重新關聯自己的產品時，不以產品資料覆寫物品現有欄位或圖片。 */
    #[Test]
    public function it_should_relink_an_owned_product_without_overwriting_item_data(): void
    {
        $user = User::factory()->create();
        $old = Product::factory()->create(['user_id' => $user->id]);
        $new = Product::factory()->create(['user_id' => $user->id, 'name' => '另一個產品']);
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'product_id' => $old->id,
            'name' => '物品原名稱',
            'price' => 45,
            'location' => '原位置',
        ]);
        $image = ItemImage::factory()->create(['user_id' => $user->id]);
        $item->images()->attach($image->uuid, ['sort_order' => 1]);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'product_id' => $new->id,
        ])->assertSuccessful()->assertJsonPath('data.product.id', $new->id);

        $updated = $item->fresh();
        $this->assertSame($new->id, $updated->product_id);
        $this->assertSame('物品原名稱', $updated->name);
        $this->assertSame(45.0, (float) $updated->price);
        $this->assertSame('原位置', $updated->location);
        $this->assertTrue($updated->images()->where('uuid', $image->uuid)->exists());
        $this->assertDatabaseCount('items', 1);
    }

    /** 只有明確傳入空值才解除產品關聯，省略欄位時保持原關聯。 */
    #[Test]
    public function it_should_only_unlink_the_product_when_null_is_explicitly_submitted(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['user_id' => $user->id]);
        $item = Item::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'name' => '保留關聯',
        ])->assertSuccessful();
        $this->assertSame($product->id, $item->fresh()->product_id);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'product_id' => null,
        ])->assertSuccessful()->assertJsonPath('data.product', null);
        $this->assertNull($item->fresh()->product_id);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** 不得透過產品 ID 關聯他人的產品，拒絕時原物品資料保持不變。 */
    #[Test]
    public function it_should_reject_foreign_product_ids_before_updating_any_item_fields(): void
    {
        $user = User::factory()->create();
        $owned = Product::factory()->create(['user_id' => $user->id]);
        $foreign = Product::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'product_id' => $owned->id,
            'name' => '原名稱',
        ]);

        $this->actingAs($user)->putJson("/api/items/{$item->short_id}", [
            'product_id' => $foreign->id,
            'name' => '不應儲存',
        ])->assertUnprocessable()->assertJsonValidationErrors('product_id');

        $this->assertSame($owned->id, $item->fresh()->product_id);
        $this->assertSame('原名稱', $item->fresh()->name);
    }
}
