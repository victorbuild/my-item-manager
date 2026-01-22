<?php

namespace Tests\Unit\Services;

use App\Services\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemService $itemService;
    private const TEST_MAX_QUANTITY = 100;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 直接注入測試值，不依賴 config，符合單元測試原則
        $this->itemService = new ItemService(self::TEST_MAX_QUANTITY);
    }

    /**
     * 測試：計算建立數量
     *
     * @test
     * @dataProvider quantityDataProvider
     */
    public function it_should_calculate_quantity_correctly(int $maxQuantity, array $data, int $expected): void
    {
        $service = new ItemService($maxQuantity);
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
}
