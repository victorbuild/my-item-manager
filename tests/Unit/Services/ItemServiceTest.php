<?php

namespace Tests\Unit\Services;

use App\Models\Item;
use App\Services\ItemImageService;
use App\Services\ItemService;
use App\Strategies\Sort\SortStrategyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemService $itemService;
    private const TEST_MAX_QUANTITY = 100;

    /**
     * @var \Mockery\MockInterface&ItemImageService
     */
    private $mockItemImageService;

    /**
     * @var \Mockery\MockInterface&SortStrategyFactory
     */
    private $mockSortStrategyFactory;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock ItemImageService，避免測試時依賴真實實作
        $this->mockItemImageService = Mockery::mock(ItemImageService::class);

        // Mock SortStrategyFactory，避免測試時依賴真實實作
        $this->mockSortStrategyFactory = Mockery::mock(SortStrategyFactory::class);

        // 直接注入測試值，不依賴 config，符合單元測試原則
        $this->itemService = new ItemService(
            self::TEST_MAX_QUANTITY,
            $this->mockItemImageService,
            $this->mockSortStrategyFactory
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 測試：計算建立數量
     *
     * @test
     * @dataProvider quantityDataProvider
     */
    public function it_should_calculate_quantity_correctly(int $maxQuantity, array $data, int $expected): void
    {
        $mockItemImageService = Mockery::mock(ItemImageService::class);
        $mockSortStrategyFactory = Mockery::mock(SortStrategyFactory::class);
        $service = new ItemService($maxQuantity, $mockItemImageService, $mockSortStrategyFactory);
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
     *
     * @test
     */
    public function it_should_create_batch_items_without_images(): void
    {
        $data = [
            'name' => '測試物品',
            'purchased_at' => now()->toDateString(),
        ];

        // 不應該呼叫 ItemImageService（因為沒有 images）
        $this->mockItemImageService->shouldNotReceive('attachImagesToItem');

        $result = $this->itemService->createBatch($data, 3);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('quantity', $result);
        $this->assertEquals(3, $result['quantity']);
        $this->assertInstanceOf(Item::class, $result['item']);
        $this->assertEquals($data['name'], $result['item']->name);
    }

    /**
     * 測試：批次建立物品（含圖片）
     *
     * @test
     */
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

        // Mock ItemImageService 的行為
        $this->mockItemImageService
            ->shouldReceive('attachImagesToItem')
            ->times(3) // 建立 3 個物品，每個都會呼叫一次
            ->with(Mockery::type(Item::class), $data['images']);

        $result = $this->itemService->createBatch($data, 3);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('item', $result);
        $this->assertArrayHasKey('quantity', $result);
        $this->assertEquals(3, $result['quantity']);
        $this->assertInstanceOf(Item::class, $result['item']);
        $this->assertEquals($data['name'], $result['item']->name);
    }

    /**
     * 測試：批次建立失敗時應該 rollback
     *
     * @test
     */
    public function it_should_rollback_when_batch_creation_fails(): void
    {
        $data = [
            'name' => '測試物品',
            'purchased_at' => now()->toDateString(),
            'images' => [
                ['uuid' => 'test-uuid-1', 'status' => 'new'],
            ],
        ];

        // Mock ItemImageService 在第二次呼叫時拋出異常
        $callCount = 0;
        $this->mockItemImageService
            ->shouldReceive('attachImagesToItem')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 2) {
                    throw new \Exception('模擬錯誤');
                }
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('模擬錯誤');

        try {
            $this->itemService->createBatch($data, 3);
        } catch (\Exception $e) {
            // 驗證資料庫中沒有建立任何物品（Transaction rollback）
            $this->assertEquals(0, Item::where('name', $data['name'])->count());
            throw $e;
        }
    }
}
