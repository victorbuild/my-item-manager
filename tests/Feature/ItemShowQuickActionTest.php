<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemShowQuickActionTest extends TestCase
{
    use RefreshDatabase;

    /** 詳情頁的局部更新可記錄使用與棄用，且不改寫照片或其他欄位。 */
    #[Test]
    public function it_should_preserve_item_details_when_recording_use_and_disposal(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'name' => '保留回憶的物品',
            'price' => 90,
            'purchased_at' => '2026-01-01',
            'received_at' => '2026-01-02',
            'used_at' => null,
            'discarded_at' => null,
            'expiration_date' => '2026-01-03',
        ]);
        $image = ItemImage::factory()->create(['user_id' => $user->id, 'usage_count' => 1]);
        $item->images()->attach($image->uuid, ['sort_order' => 1]);

        $this->actingAs($user)->patchJson("/api/items/{$item->short_id}", [
            'used_at' => '2026-01-04',
        ])->assertSuccessful()->assertJsonPath('data.status', 'in_use');

        $this->patchJson("/api/items/{$item->short_id}", [
            'discarded_at' => '2026-01-05',
            'discard_note' => '留下回憶',
        ])->assertSuccessful()->assertJsonPath('data.status', 'used_discarded');

        $updated = $item->fresh();
        $this->assertSame('保留回憶的物品', $updated->name);
        $this->assertSame(90.0, (float) $updated->price);
        $this->assertSame('留下回憶', $updated->discard_note);
        $this->assertSame('2026-01-03', $updated->expiration_date->format('Y-m-d'));
        $this->assertSame(1, $updated->images()->count());
        $this->assertSame(1, $image->fresh()->usage_count);
    }

    /** 使用中也能單獨保存回憶，不應因此變成棄用或補寫缺少的日期。 */
    #[Test]
    public function it_should_keep_usage_state_when_saving_only_a_discard_note(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'purchased_at' => '2026-01-01',
            'received_at' => null,
            'used_at' => '2026-01-02',
            'discarded_at' => null,
        ]);

        $this->actingAs($user)->patchJson("/api/items/{$item->short_id}", [
            'discard_note' => '還在使用，也想記下回憶',
        ])->assertSuccessful()->assertJsonPath('data.status', 'in_use');

        $this->assertNull($item->fresh()->received_at);
        $this->assertNull($item->fresh()->discarded_at);
    }

    /** 衝突的購買或到貨日期應拒絕更新，原日期不得被交換或改寫。 */
    #[Test]
    public function it_should_preserve_dates_when_a_quick_update_conflicts_with_existing_dates(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id,
            'purchased_at' => '2026-10-01',
            'received_at' => '2026-10-02',
            'used_at' => null,
            'discarded_at' => null,
        ]);

        foreach (['purchased_at' => '2026-10-30', 'received_at' => '2026-09-30'] as $field => $date) {
            $this->actingAs($user)->patchJson("/api/items/{$item->short_id}", [
                $field => $date,
            ])->assertUnprocessable()->assertJsonValidationErrors($field);

            $this->assertSame('2026-10-01', $item->fresh()->purchased_at->format('Y-m-d'));
            $this->assertSame('2026-10-02', $item->fresh()->received_at->format('Y-m-d'));
        }
    }
}
