<?php

namespace Tests\Unit\Strategies\Sort;

use App\Strategies\Sort\DefaultSortStrategy;
use App\Strategies\Sort\DiscardedSortStrategy;
use App\Strategies\Sort\NameAscSortStrategy;
use App\Strategies\Sort\PriceAscSortStrategy;
use App\Strategies\Sort\PriceDescSortStrategy;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 排序策略單元測試
 *
 * 雖然策略類別很簡單，但保留測試作為：
 * 1. 第一道防線：如果有人修改策略，測試會立即失敗
 * 2. 文檔作用：說明每個策略應該做什麼
 * 3. 快速反饋：單元測試執行速度快，可以快速發現問題
 *
 * 注意：這些測試只驗證策略是否正確呼叫了查詢建構器的方法
 * 實際的排序效果應該在整合測試中驗證（Feature/Services/ItemServiceTest）
 */
class SortStrategyTest extends TestCase
{
    /**
     * 測試：預設排序策略
     *
     */
    #[Test]
    public function it_should_apply_default_sort_strategy(): void
    {
        $strategy = new DefaultSortStrategy();
        $query = Mockery::mock(Builder::class);

        $query->shouldReceive('orderByDesc')
            ->once()
            ->with('id')
            ->andReturnSelf();

        $strategy->apply($query);

        $this->assertTrue(true);
    }

    /**
     * 測試：棄用時間排序策略
     *
     */
    #[Test]
    public function it_should_apply_discarded_sort_strategy(): void
    {
        $strategy = new DiscardedSortStrategy();
        $query = Mockery::mock(Builder::class);

        $query->shouldReceive('orderByDesc')
            ->once()
            ->with('discarded_at')
            ->andReturnSelf();

        $strategy->apply($query);

        $this->assertTrue(true);
    }

    /**
     * 測試：價格升序排序策略
     *
     */
    #[Test]
    public function it_should_apply_price_asc_sort_strategy(): void
    {
        $strategy = new PriceAscSortStrategy();
        $query = Mockery::mock(Builder::class);

        $query->shouldReceive('orderBy')
            ->once()
            ->with('price', 'asc')
            ->andReturnSelf();

        $strategy->apply($query);

        $this->assertTrue(true);
    }

    /**
     * 測試：價格降序排序策略
     *
     */
    #[Test]
    public function it_should_apply_price_desc_sort_strategy(): void
    {
        $strategy = new PriceDescSortStrategy();
        $query = Mockery::mock(Builder::class);

        $query->shouldReceive('orderByDesc')
            ->once()
            ->with('price')
            ->andReturnSelf();

        $strategy->apply($query);

        $this->assertTrue(true);
    }

    /**
     * 測試：名稱升序排序策略
     *
     */
    #[Test]
    public function it_should_apply_name_asc_sort_strategy(): void
    {
        $strategy = new NameAscSortStrategy();
        $query = Mockery::mock(Builder::class);

        $query->shouldReceive('orderBy')
            ->once()
            ->with('name', 'asc')
            ->andReturnSelf();

        $strategy->apply($query);

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
