<?php

namespace Tests\Unit\Services;

use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Services\ItemImageService;
use App\Services\ItemService;
use App\Strategies\Sort\SortStrategyFactory;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    private ItemService $itemService;
    private const TEST_MAX_QUANTITY = 100;
    private const TEST_USER_ID = 1;

    /**
     * @var \Mockery\MockInterface&ItemImageService
     */
    private $mockItemImageService;

    /**
     * @var \Mockery\MockInterface&SortStrategyFactory
     */
    private $mockSortStrategyFactory;

    /**
     * @var \Mockery\MockInterface&ItemRepositoryInterface
     */
    private $mockItemRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock ItemImageService，避免測試時依賴真實實作
        $this->mockItemImageService = Mockery::mock(ItemImageService::class);

        // Mock SortStrategyFactory，避免測試時依賴真實實作
        $this->mockSortStrategyFactory = Mockery::mock(SortStrategyFactory::class);

        // Mock ItemRepository，避免測試時依賴真實資料庫
        $this->mockItemRepository = Mockery::mock(ItemRepositoryInterface::class);

        // 直接注入測試值，不依賴 config，符合單元測試原則
        $this->itemService = new ItemService(
            self::TEST_MAX_QUANTITY,
            $this->mockItemImageService,
            $this->mockSortStrategyFactory,
            $this->mockItemRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 測試：計算建立數量
     */
    #[Test]
    #[DataProvider('quantityDataProvider')]
    public function it_should_calculate_quantity_correctly(int $maxQuantity, array $data, int $expected): void
    {
        $mockItemImageService = Mockery::mock(ItemImageService::class);
        $mockSortStrategyFactory = Mockery::mock(SortStrategyFactory::class);
        $mockItemRepository = Mockery::mock(ItemRepositoryInterface::class);
        $service = new ItemService($maxQuantity, $mockItemImageService, $mockSortStrategyFactory, $mockItemRepository);
        $result = $service->calculateQuantity($data);

        $this->assertEquals($expected, $result);
        $this->assertIsInt($result);
    }

    /**
     * 測試資料提供者
     *
     * @return array<string, array{0: int, 1: array, 2: int}>
     */
    public static function quantityDataProvider(): array
    {
        $defaultMaxQuantity = self::TEST_MAX_QUANTITY;

        return [
            // 基本邊界條件測試（使用預設 maxQuantity = 100）
            'should return default 1 when quantity is null' => [
                $defaultMaxQuantity,
                ['name' => '測試物品'],
                1,
            ],
            'should return minimum 1 when quantity is 0' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => 0],
                1,
            ],
            'should return minimum 1 when quantity is negative' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => -5],
                1,
            ],
            'should return original value when quantity is within valid range' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => 5],
                5,
            ],
            'should return 10 when quantity is 10' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => 10],
                10,
            ],
            'should return max quantity when quantity equals limit' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => $defaultMaxQuantity],
                $defaultMaxQuantity,
            ],
            'should return max quantity when quantity exceeds limit' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => $defaultMaxQuantity + 50],
                $defaultMaxQuantity,
            ],
            'should convert string quantity to integer' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => '10'],
                10,
            ],
            'should convert float quantity to integer' => [
                $defaultMaxQuantity,
                ['name' => '測試物品', 'quantity' => 5.7],
                5,
            ],
            // 測試不同的 maxQuantity 值，驗證依賴注入是否正確
            'should respect maxQuantity = 50 when quantity = 60' => [
                50,
                ['name' => '測試物品', 'quantity' => 60],
                50,
            ],
            'should respect maxQuantity = 200 when quantity = 150' => [
                200,
                ['name' => '測試物品', 'quantity' => 150],
                150,
            ],
            'should respect maxQuantity = 10 when quantity = 5' => [
                10,
                ['name' => '測試物品', 'quantity' => 5],
                5,
            ],
            'should respect maxQuantity = 1 when quantity = 5' => [
                1,
                ['name' => '測試物品', 'quantity' => 5],
                1,
            ],
        ];
    }

    /**
     * 測試：批次建立物品（不含圖片）
     */
    #[Test]
    public function it_should_create_batch_items_without_images(): void
    {
        $data = [
            'name' => '測試物品',
            'purchased_at' => now()->toDateString(),
        ];

        // 使用真實的 Item 實例（不保存到資料庫，只是作為返回值）
        $item1 = new Item($data);
        $item2 = new Item($data);
        $item3 = new Item($data);
        $items = [$item1, $item2, $item3];

        $this->mockItemRepository
            ->shouldReceive('createBatch')
            ->once()
            ->with($data, 3, self::TEST_USER_ID)
            ->andReturn([
                'items' => $items,
                'item' => $item1,
                'quantity' => 3,
            ]);

        // 不應該呼叫 ItemImageService（因為沒有 images）
        $this->mockItemImageService->shouldNotReceive('attachImagesToItem');

        $result = $this->itemService->createBatch($data, 3, self::TEST_USER_ID);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('quantity', $result);
        $this->assertEquals(3, $result['quantity']);
        $this->assertCount(3, $result['items']);
        $this->assertInstanceOf(Item::class, $result['item']);
        $this->assertSame($item1, $result['item']);
    }

    /**
     * 測試：批次建立物品（含圖片）
     */
    #[Test]
    public function it_should_create_batch_items_with_images(): void
    {
        $data = [
            'name' => '測試物品',
            'purchased_at' => now()->toDateString(),
            'images' => [
                ['uuid' => 'test-uuid-1', 'status' => 'new'],
                ['uuid' => 'test-uuid-2', 'status' => 'new'],
            ],
        ];

        // 使用真實的 Item 實例（不保存到資料庫，只是作為返回值）
        $item1 = new Item($data);
        $item2 = new Item($data);
        $item3 = new Item($data);
        $items = [$item1, $item2, $item3];

        $this->mockItemRepository
            ->shouldReceive('createBatch')
            ->once()
            ->with($data, 3, self::TEST_USER_ID)
            ->andReturn([
                'items' => $items,
                'item' => $item1,
                'quantity' => 3,
            ]);

        // Mock ItemImageService 的行為（為所有物品附加圖片）
        $this->mockItemImageService
            ->shouldReceive('attachImagesToItem')
            ->once()
            ->with($item1, $data['images']);

        $this->mockItemImageService
            ->shouldReceive('attachImagesToItem')
            ->once()
            ->with($item2, $data['images']);

        $this->mockItemImageService
            ->shouldReceive('attachImagesToItem')
            ->once()
            ->with($item3, $data['images']);

        $result = $this->itemService->createBatch($data, 3, self::TEST_USER_ID);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('quantity', $result);
        $this->assertEquals(3, $result['quantity']);
        $this->assertCount(3, $result['items']);
        $this->assertInstanceOf(Item::class, $result['item']);
        $this->assertSame($item1, $result['item']);
    }

    /**
     * 測試：批次建立失敗時應該 rollback
     */
    #[Test]
    public function it_should_rollback_when_batch_creation_fails(): void
    {
        $data = [
            'name' => '測試物品',
            'purchased_at' => now()->toDateString(),
            'images' => [
                ['uuid' => 'test-uuid-1', 'status' => 'new'],
            ],
        ];

        // 使用真實的 Item 實例（不保存到資料庫，只是作為返回值）
        $item1 = new Item($data);
        $item2 = new Item($data);
        $item3 = new Item($data);
        $items = [$item1, $item2, $item3];

        // Mock ItemRepository 的 createBatch 成功返回
        $this->mockItemRepository
            ->shouldReceive('createBatch')
            ->once()
            ->with($data, 3, self::TEST_USER_ID)
            ->andReturn([
                'items' => $items,
                'item' => $item1,
                'quantity' => 3,
            ]);

        // Mock ItemImageService 在為第二個物品附加圖片時拋出異常（模擬圖片附加失敗）
        $this->mockItemImageService
            ->shouldReceive('attachImagesToItem')
            ->once()
            ->with($item1, $data['images']);

        $this->mockItemImageService
            ->shouldReceive('attachImagesToItem')
            ->once()
            ->with($item2, $data['images'])
            ->andThrow(new \Exception('模擬錯誤'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('模擬錯誤');

        // Act & Assert
        $this->itemService->createBatch($data, 3, self::TEST_USER_ID);
    }

    /**
     * 測試：更新物品基本資料
     */
    #[Test]
    public function it_should_update_item_basic_data(): void
    {
        // Arrange
        $item = new Item(['name' => '原始名稱', 'price' => 1000]);
        $updatedItem = new Item(['name' => '更新後的名稱', 'price' => 2000]);

        // Mock ItemRepository（會自動 fresh 關聯資料）
        $this->mockItemRepository
            ->shouldReceive('update')
            ->once()
            ->with($item, ['name' => '更新後的名稱', 'price' => 2000])
            ->andReturn($updatedItem);

        $data = [
            'name' => '更新後的名稱',
            'price' => 2000,
        ];

        // Mock ItemImageService（不應該被呼叫）
        $this->mockItemImageService
            ->shouldNotReceive('syncItemImages');

        // Act
        $result = $this->itemService->update($item, $data);

        // Assert
        $this->assertInstanceOf(Item::class, $result);
        $this->assertSame($updatedItem, $result);
    }

    /**
     * 測試：更新物品並同步圖片
     */
    #[Test]
    public function it_should_update_item_and_sync_images(): void
    {
        // Arrange
        $item = new Item(['name' => '原始名稱']);
        $updatedItem = Mockery::mock(Item::class)->makePartial();
        $finalItem = new Item(['name' => '更新後的名稱']); // 同步圖片後重新載入的 Item

        // Mock ItemRepository（會自動 fresh 關聯資料）
        $this->mockItemRepository
            ->shouldReceive('update')
            ->once()
            ->with($item, ['name' => '更新後的名稱'])
            ->andReturn($updatedItem);

        $data = [
            'name' => '更新後的名稱',
        ];

        $images = [
            ['uuid' => 'uuid1', 'status' => 'new'],
            ['uuid' => 'uuid2', 'status' => 'removed'],
        ];

        // Mock ItemImageService（會自動 fresh 關聯資料並返回）
        $this->mockItemImageService
            ->shouldReceive('syncItemImages')
            ->with($updatedItem, $images)
            ->once()
            ->andReturn($finalItem);

        // Act
        $result = $this->itemService->update($item, $data, $images);

        // Assert
        $this->assertInstanceOf(Item::class, $result);
        $this->assertSame($finalItem, $result);
    }

    /**
     * 測試：更新物品但不提供圖片（images 為空陣列）
     */
    #[Test]
    public function it_should_update_item_without_images_when_images_is_empty(): void
    {
        // Arrange
        $item = new Item(['name' => '原始名稱']);
        $updatedItem = new Item(['name' => '更新後的名稱']);

        // Mock ItemRepository（會自動 fresh 關聯資料）
        $this->mockItemRepository
            ->shouldReceive('update')
            ->once()
            ->with($item, ['name' => '更新後的名稱'])
            ->andReturn($updatedItem);

        $data = [
            'name' => '更新後的名稱',
        ];

        // Mock ItemImageService（不應該被呼叫）
        $this->mockItemImageService
            ->shouldNotReceive('syncItemImages');

        // Act
        $result = $this->itemService->update($item, $data, []);

        // Assert
        $this->assertInstanceOf(Item::class, $result);
        $this->assertSame($updatedItem, $result);
    }

    /**
     * 測試：更新物品後載入關聯資料
     */
    #[Test]
    public function it_should_load_relationships_after_update(): void
    {
        // Arrange
        $item = new Item(['name' => '原始名稱']);
        $updatedItem = new Item(['name' => '更新後的名稱']);

        // Mock ItemRepository（會自動 fresh 關聯資料）
        $this->mockItemRepository
            ->shouldReceive('update')
            ->once()
            ->with($item, ['name' => '更新後的名稱'])
            ->andReturn($updatedItem);

        $data = [
            'name' => '更新後的名稱',
        ];

        // Mock ItemImageService（不應該被呼叫，因為 images 為空陣列）
        $this->mockItemImageService
            ->shouldNotReceive('syncItemImages');

        // Act
        $result = $this->itemService->update($item, $data, []);

        // Assert
        $this->assertInstanceOf(Item::class, $result);
        // 驗證 Repository 會自動 fresh 關聯資料
        $this->assertSame($updatedItem, $result);
    }

    /**
     * 測試：根據 short_id 查詢物品 - 成功
     */
    #[Test]
    public function it_should_find_item_by_short_id_successfully(): void
    {
        // Arrange
        $shortId = 'test1234567';
        $expectedItem = new Item();
        $expectedItem->short_id = $shortId;
        $expectedItem->name = '測試物品';

        $this->mockItemRepository
            ->shouldReceive('findByShortIdOrFail')
            ->once()
            ->with($shortId)
            ->andReturn($expectedItem);

        // Act
        $result = $this->itemService->findByShortIdOrFail($shortId);

        // Assert
        $this->assertInstanceOf(Item::class, $result);
        $this->assertSame($expectedItem, $result);
        $this->assertEquals($shortId, $result->short_id);
    }

    /**
     * 測試：根據 short_id 查詢物品 - 找不到時拋出異常
     */
    #[Test]
    public function it_should_throw_exception_when_item_not_found_by_short_id(): void
    {
        // Arrange
        $shortId = 'nonexistent';
        $exception = new \Illuminate\Database\Eloquent\ModelNotFoundException();

        $this->mockItemRepository
            ->shouldReceive('findByShortIdOrFail')
            ->once()
            ->with($shortId)
            ->andThrow($exception);

        // Act & Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->itemService->findByShortIdOrFail($shortId);
    }

    /**
     * 測試：查詢近期過期的商品 - 應該呼叫 Repository 並傳入正確的參數
     */
    #[Test]
    public function it_should_call_repository_with_correct_parameters_for_expiring_soon_items(): void
    {
        // Arrange
        $days = 30;
        $perPage = 20;
        $userId = self::TEST_USER_ID;
        $expectedPaginator = new LengthAwarePaginator(
            [],
            0,
            $perPage,
            1
        );

        $this->mockItemRepository
            ->shouldReceive('getExpiringSoonItems')
            ->once()
            ->with($days, $perPage, $userId)
            ->andReturn($expectedPaginator);

        // Act
        $result = $this->itemService->getExpiringSoonItems($userId, $days, $perPage);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame($expectedPaginator, $result);
    }

    /**
     * 測試：查詢近期過期的商品 - 應該使用傳入的 userId 而非 auth()->id()
     */
    #[Test]
    public function it_should_use_provided_user_id_instead_of_auth_id(): void
    {
        // Arrange
        $days = 30;
        $perPage = 20;
        $userId = 999; // 不同的 userId
        $expectedPaginator = new LengthAwarePaginator(
            [],
            0,
            $perPage,
            1
        );

        $this->mockItemRepository
            ->shouldReceive('getExpiringSoonItems')
            ->once()
            ->with($days, $perPage, $userId)
            ->andReturn($expectedPaginator);

        // Act
        $result = $this->itemService->getExpiringSoonItems($userId, $days, $perPage);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame($expectedPaginator, $result);
        // 驗證 Repository 被呼叫時使用的是傳入的 userId（透過 shouldReceive 的 with 驗證）
    }

    /**
     * 測試：取得 expiringSoon 統計資料 - 應該呼叫 Repository 方法並回傳正確結構
     */
    #[Test]
    public function it_should_call_repository_methods_for_expiring_soon_statistics(): void
    {
        // Arrange
        $days = 30;
        $userId = self::TEST_USER_ID;
        $ranges = [7, 30, 90, 180, 365, 1095];
        $expectedRangeStats = [
            7 => 5,
            30 => 10,
            90 => 15,
            180 => 20,
            365 => 25,
            1095 => 30,
        ];
        $expectedTotalCount = 50;

        $this->mockItemRepository
            ->shouldReceive('getRangeStatistics')
            ->once()
            ->with($ranges, $userId)
            ->andReturn($expectedRangeStats);

        $this->mockItemRepository
            ->shouldReceive('countItemsWithExpirationDate')
            ->once()
            ->with($userId)
            ->andReturn($expectedTotalCount);

        // Act
        $result = $this->itemService->getExpiringSoonStatistics($days, $userId);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('range_statistics', $result);
        $this->assertArrayHasKey('total_all_with_expiration_date', $result);
        $this->assertSame($expectedRangeStats, $result['range_statistics']);
        $this->assertEquals($expectedTotalCount, $result['total_all_with_expiration_date']);
    }

    /**
     * 測試：取得 expiringSoon 統計資料 - 應該使用傳入的 userId 而非 auth()->id()
     */
    #[Test]
    public function it_should_use_provided_user_id_for_expiring_soon_statistics(): void
    {
        // Arrange
        $days = 30;
        $userId = 999; // 不同的 userId
        $ranges = [7, 30, 90, 180, 365, 1095];
        $expectedRangeStats = [
            7 => 2,
            30 => 5,
            90 => 8,
            180 => 12,
            365 => 15,
            1095 => 20,
        ];
        $expectedTotalCount = 25;

        $this->mockItemRepository
            ->shouldReceive('getRangeStatistics')
            ->once()
            ->with($ranges, $userId)
            ->andReturn($expectedRangeStats);

        $this->mockItemRepository
            ->shouldReceive('countItemsWithExpirationDate')
            ->once()
            ->with($userId)
            ->andReturn($expectedTotalCount);

        // Act
        $result = $this->itemService->getExpiringSoonStatistics($days, $userId);

        // Assert
        $this->assertIsArray($result);
        $this->assertSame($expectedRangeStats, $result['range_statistics']);
        $this->assertEquals($expectedTotalCount, $result['total_all_with_expiration_date']);
        // 驗證 Repository 被呼叫時使用的是傳入的 userId
    }
}
