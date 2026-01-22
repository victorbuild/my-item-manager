<?php

namespace Tests\Unit\Strategies\Sort;

use App\Strategies\Sort\DefaultSortStrategy;
use App\Strategies\Sort\DiscardedSortStrategy;
use App\Strategies\Sort\NameAscSortStrategy;
use App\Strategies\Sort\PriceAscSortStrategy;
use App\Strategies\Sort\PriceDescSortStrategy;
use App\Strategies\Sort\SortStrategyFactory;
use InvalidArgumentException;
use Tests\TestCase;

class SortStrategyFactoryTest extends TestCase
{
    private SortStrategyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new SortStrategyFactory();
    }

    /**
     * 測試：建立預設策略
     *
     * @test
     */
    public function it_should_create_default_strategy(): void
    {
        $strategy = $this->factory->create('default');

        $this->assertInstanceOf(DefaultSortStrategy::class, $strategy);
    }

    /**
     * 測試：建立棄用時間排序策略
     *
     * @test
     */
    public function it_should_create_discarded_strategy(): void
    {
        $strategy = $this->factory->create('discarded');

        $this->assertInstanceOf(DiscardedSortStrategy::class, $strategy);
    }

    /**
     * 測試：建立價格升序排序策略
     *
     * @test
     */
    public function it_should_create_price_asc_strategy(): void
    {
        $strategy = $this->factory->create('price_asc');

        $this->assertInstanceOf(PriceAscSortStrategy::class, $strategy);
    }

    /**
     * 測試：建立價格降序排序策略
     *
     * @test
     */
    public function it_should_create_price_desc_strategy(): void
    {
        $strategy = $this->factory->create('price_desc');

        $this->assertInstanceOf(PriceDescSortStrategy::class, $strategy);
    }

    /**
     * 測試：建立名稱升序排序策略
     *
     * @test
     */
    public function it_should_create_name_asc_strategy(): void
    {
        $strategy = $this->factory->create('name_asc');

        $this->assertInstanceOf(NameAscSortStrategy::class, $strategy);
    }

    /**
     * 測試：不支援的排序模式應該拋出異常
     *
     * @test
     */
    public function it_should_throw_exception_for_invalid_sort_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('不支援的排序模式: invalid');

        $this->factory->create('invalid');
    }
}
