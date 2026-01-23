<?php

namespace Tests\Feature\Services;

use App\Models\Item;
use App\Models\User;
use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        // 使用真實的依賴，測試完整的整合
        $this->service = app(ItemService::class);
    }

    /**
     * 測試：預設排序（按 ID 降序）
     */
    #[Test]
    public function it_should_sort_items_by_default_strategy(): void
    {
        // Arrange
        $items = Item::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'name' => '測試物品',
        ]);

        // Act
        $result = $this->service->paginateWithFilters(['sort' => 'default'], $this->user->id);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(5, $result->total());

        // 驗證排序：ID 應該是降序（最新的在前）
        $ids = $result->pluck('id')->toArray();
        $sortedIds = $ids;
        rsort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }

    /**
     * 測試：棄用時間排序（按 discarded_at 降序）
     */
    #[Test]
    public function it_should_sort_items_by_discarded_strategy(): void
    {
        // Arrange
        $item1 = Item::factory()->create([
            'user_id' => $this->user->id,
            'discarded_at' => now()->subDays(5),
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $this->user->id,
            'discarded_at' => now()->subDays(2),
        ]);
        $item3 = Item::factory()->create([
            'user_id' => $this->user->id,
            'discarded_at' => now()->subDays(10),
        ]);

        // Act
        $result = $this->service->paginateWithFilters(['sort' => 'discarded'], $this->user->id);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->total());

        // 驗證排序：discarded_at 應該是降序（最新的在前）
        $discardedDates = $result->pluck('discarded_at')->toArray();
        $sortedDates = $discardedDates;
        rsort($sortedDates);
        $this->assertEquals($sortedDates, $discardedDates);
    }

    /**
     * 測試：價格升序排序
     */
    #[Test]
    public function it_should_sort_items_by_price_asc_strategy(): void
    {
        // Arrange
        Item::factory()->create([
            'user_id' => $this->user->id,
            'price' => 1000,
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'price' => 500,
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'price' => 1500,
        ]);

        // Act
        $result = $this->service->paginateWithFilters(['sort' => 'price_asc'], $this->user->id);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->total());

        // 驗證排序：價格應該是升序
        $prices = $result->pluck('price')->toArray();
        $sortedPrices = $prices;
        sort($sortedPrices);
        $this->assertEquals($sortedPrices, $prices);
    }

    /**
     * 測試：價格降序排序
     */
    #[Test]
    public function it_should_sort_items_by_price_desc_strategy(): void
    {
        // Arrange
        Item::factory()->create([
            'user_id' => $this->user->id,
            'price' => 1000,
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'price' => 500,
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'price' => 1500,
        ]);

        // Act
        $result = $this->service->paginateWithFilters(['sort' => 'price_desc'], $this->user->id);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->total());

        // 驗證排序：價格應該是降序
        $prices = $result->pluck('price')->toArray();
        $sortedPrices = $prices;
        rsort($sortedPrices);
        $this->assertEquals($sortedPrices, $prices);
    }

    /**
     * 測試：名稱升序排序
     */
    #[Test]
    public function it_should_sort_items_by_name_asc_strategy(): void
    {
        // Arrange
        Item::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'C 物品',
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'A 物品',
        ]);
        Item::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'B 物品',
        ]);

        // Act
        $result = $this->service->paginateWithFilters(['sort' => 'name_asc'], $this->user->id);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->total());

        // 驗證排序：名稱應該是升序
        $names = $result->pluck('name')->toArray();
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }
}
