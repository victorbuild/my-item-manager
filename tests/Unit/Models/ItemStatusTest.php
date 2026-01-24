<?php

namespace Tests\Unit\Models;

use App\Enums\ItemStatus;
use App\Models\Item;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 測試 Item 狀態計算邏輯
 * 確保所有 16 種日期組合都能正確計算狀態
 */
class ItemStatusTest extends TestCase
{
    /**
     * 測試 getStatusFromDates() 方法的所有狀態組合
     *
     * @param Carbon|string|null $discardedAt
     * @param Carbon|string|null $usedAt
     * @param Carbon|string|null $receivedAt
     * @param string $expectedStatus
     * @param string $description
     */
    #[Test]
    #[DataProvider('statusCombinationsProvider')]
    public function it_should_calculate_status_correctly_for_all_combinations(
        $discardedAt,
        $usedAt,
        $receivedAt,
        string $expectedStatus,
        string $description
    ): void {
        $actualStatus = Item::getStatusFromDates($discardedAt, $usedAt, $receivedAt);

        $this->assertEquals(
            $expectedStatus,
            $actualStatus,
            "狀態計算錯誤：{$description}"
        );
    }

    /**
     * 提供所有 16 種狀態組合的測試資料
     * 注意：purchased_at 不影響狀態判斷，所以實際測試的是
     * discarded_at, used_at, received_at 的組合
     *
     * @return array<int, array{0: Carbon|string|null, 1: Carbon|string|null,
     *                          2: Carbon|string|null, 3: string, 4: string}>
     */
    public static function statusCombinationsProvider(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $threeDaysAgo = Carbon::parse('-3 days');

        return [
            // pre_arrival (尚未到貨) - 4 種組合（表格中的組合，purchased_at 不同但不影響狀態）
            [
                null, // discarded_at
                null, // used_at
                null, // received_at
                ItemStatus::PRE_ARRIVAL->value,
                '尚未到貨（完全空白）',
            ],
            [
                null,
                null,
                null,
                ItemStatus::PRE_ARRIVAL->value,
                '尚未到貨（已購買但未到貨）- purchased_at 有值但不影響狀態',
            ],

            // unused (未使用) - 2 種組合
            [
                null,
                null,
                $today,
                ItemStatus::UNUSED->value,
                '未使用（已到貨但未使用）- received_at 有值',
            ],
            [
                null,
                null,
                $today,
                ItemStatus::UNUSED->value,
                '未使用（已購買且已到貨，但未使用）- purchased_at 和 received_at 都有值',
            ],

            // in_use (使用中) - 4 種組合
            [
                null,
                $today,
                null,
                ItemStatus::IN_USE->value,
                '使用中（直接使用，跳過到貨）- used_at 有值，received_at 為 null',
            ],
            [
                null,
                $today,
                null,
                ItemStatus::IN_USE->value,
                '使用中（已購買，直接使用）- purchased_at 和 used_at 有值，received_at 為 null',
            ],
            [
                null,
                $today,
                $yesterday,
                ItemStatus::IN_USE->value,
                '使用中（已到貨且使用）- received_at 和 used_at 都有值',
            ],
            [
                null,
                $today,
                $yesterday,
                ItemStatus::IN_USE->value,
                '使用中（完整流程）- purchased_at, received_at, used_at 都有值',
            ],

            // unused_discarded (未使用就棄用) - 4 種組合
            [
                $today,
                null,
                null,
                ItemStatus::UNUSED_DISCARDED->value,
                '未使用就棄用（完全空白就棄用）- discarded_at 有值，其他為 null',
            ],
            [
                $today,
                null,
                null,
                ItemStatus::UNUSED_DISCARDED->value,
                '未使用就棄用（已購買但未到貨就棄用）- purchased_at 和 discarded_at 有值',
            ],
            [
                $today,
                null,
                $yesterday,
                ItemStatus::UNUSED_DISCARDED->value,
                '未使用就棄用（已到貨但未使用就棄用）- received_at 和 discarded_at 有值，used_at 為 null',
            ],
            [
                $today,
                null,
                $yesterday,
                ItemStatus::UNUSED_DISCARDED->value,
                '未使用就棄用（已購買且已到貨但未使用就棄用）- purchased_at, received_at, discarded_at 有值，used_at 為 null',
            ],

            // used_discarded (使用後棄用) - 4 種組合
            [
                $today,
                $yesterday,
                null,
                ItemStatus::USED_DISCARDED->value,
                '使用後棄用（直接使用後棄用）- used_at 和 discarded_at 有值，received_at 為 null',
            ],
            [
                $today,
                $yesterday,
                null,
                ItemStatus::USED_DISCARDED->value,
                '使用後棄用（已購買，使用後棄用）- purchased_at, used_at, discarded_at 有值',
            ],
            [
                $today,
                $yesterday,
                $threeDaysAgo,
                ItemStatus::USED_DISCARDED->value,
                '使用後棄用（已到貨，使用後棄用）- received_at, used_at, discarded_at 有值',
            ],
            [
                $today,
                $yesterday,
                $threeDaysAgo,
                ItemStatus::USED_DISCARDED->value,
                '使用後棄用（完整流程後棄用）- purchased_at, received_at, used_at, discarded_at 都有值',
            ],
        ];
    }

    /**
     * 測試狀態判斷的優先順序
     * 確保 discarded_at > used_at > received_at 的優先順序正確
     */
    #[Test]
    public function it_should_prioritize_discarded_at_over_used_at(): void
    {
        $discardedAt = Carbon::today();
        $usedAt = Carbon::yesterday();

        $status = Item::getStatusFromDates($discardedAt, $usedAt, null);

        $this->assertEquals(ItemStatus::USED_DISCARDED->value, $status);
    }

    #[Test]
    public function it_should_prioritize_discarded_at_over_received_at(): void
    {
        $discardedAt = Carbon::today();
        $receivedAt = Carbon::yesterday();

        $status = Item::getStatusFromDates($discardedAt, null, $receivedAt);

        $this->assertEquals(ItemStatus::UNUSED_DISCARDED->value, $status);
    }

    #[Test]
    public function it_should_prioritize_used_at_over_received_at(): void
    {
        $usedAt = Carbon::today();
        $receivedAt = Carbon::yesterday();

        $status = Item::getStatusFromDates(null, $usedAt, $receivedAt);

        $this->assertEquals(ItemStatus::IN_USE->value, $status);
    }

    /**
     * 測試狀態判斷的邊界情況
     */
    #[Test]
    public function it_should_handle_all_null_values(): void
    {
        $status = Item::getStatusFromDates(null, null, null);

        $this->assertEquals(ItemStatus::PRE_ARRIVAL->value, $status);
    }

    #[Test]
    public function it_should_handle_string_date_values(): void
    {
        $discardedAt = '2026-01-24';
        $usedAt = '2026-01-23';

        $status = Item::getStatusFromDates($discardedAt, $usedAt, null);

        $this->assertEquals(ItemStatus::USED_DISCARDED->value, $status);
    }

    #[Test]
    public function it_should_handle_carbon_date_values(): void
    {
        $discardedAt = Carbon::parse('2026-01-24');
        $usedAt = Carbon::parse('2026-01-23');

        $status = Item::getStatusFromDates($discardedAt, $usedAt, null);

        $this->assertEquals(ItemStatus::USED_DISCARDED->value, $status);
    }
}
