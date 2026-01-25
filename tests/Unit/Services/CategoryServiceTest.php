<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Item;
use App\Models\Product;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CategoryService;
use Illuminate\Support\Collection;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    /**
     * @var \Mockery\MockInterface&CategoryRepositoryInterface
     */
    private $mockRepository;

    private CategoryService $service;

    private int $testUserId = 1;

    private array $testCategoryData = ['name' => '測試分類'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepository = Mockery::mock(CategoryRepositoryInterface::class);
        $this->service = new CategoryService($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delete_should_return_true_when_category_has_no_products(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;

        $this->mockRepository
            ->shouldReceive('getProductsCount')
            ->once()
            ->with(1, $this->testUserId)
            ->andReturn(0);

        $this->mockRepository
            ->shouldReceive('delete')
            ->once()
            ->with($category)
            ->andReturn(true);

        $result = $this->service->delete($category);

        $this->assertTrue($result);
    }

    public function test_delete_should_throw_exception_when_category_has_products(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;

        $this->mockRepository
            ->shouldReceive('getProductsCount')
            ->once()
            ->with(1, $this->testUserId)
            ->andReturn(3);

        $this->mockRepository
            ->shouldReceive('delete')
            ->never();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('無法刪除此分類，因為還有 3 個產品關聯此分類。');

        $this->service->delete($category);
    }

    public function test_get_category_with_stats_should_return_category_with_stats_when_category_exists(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;
        $category->name = '測試分類';

        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->id = 1;
        $product1->short_id = 'PRD001';
        $product1->name = '產品1';
        $product1->brand = '品牌1';

        $product2 = Mockery::mock(Product::class)->makePartial();
        $product2->id = 2;
        $product2->short_id = 'PRD002';
        $product2->name = '產品2';
        $product2->brand = null;

        $item1 = Mockery::mock(Item::class)->makePartial();
        $item1->discarded_at = null;
        $item1->used_at = now();
        $item1->received_at = now()->subDays(10);

        $item2 = Mockery::mock(Item::class)->makePartial();
        $item2->discarded_at = null;
        $item2->used_at = null;
        $item2->received_at = now()->subDays(5);

        $item3 = Mockery::mock(Item::class)->makePartial();
        $item3->discarded_at = null;
        $item3->used_at = null;
        $item3->received_at = null;

        $item4 = Mockery::mock(Item::class)->makePartial();
        $item4->discarded_at = now();
        $item4->used_at = now()->subDays(1);
        $item4->received_at = now()->subDays(10);

        $product1->items = Collection::make([$item1, $item2]);
        $product2->items = Collection::make([$item3, $item4]);

        $allProducts = Collection::make([$product1, $product2]);
        $products = Collection::make([$product1, $product2]);
        $allItems = Collection::make([$item1, $item2, $item3, $item4]);

        $this->mockRepository
            ->shouldReceive('getCategoryWithRelations')
            ->once()
            ->with($category, 1, 10)
            ->andReturn([
                'category' => $category,
                'products' => $products,
                'all_products' => $allProducts,
                'all_items' => $allItems,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 2,
                ],
            ]);

        $result = $this->service->getCategoryWithStats($category, 1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('products', $result);
        $this->assertArrayHasKey('meta', $result);

        $stats = $result['stats'];
        $this->assertEquals(2, $stats['products_count']);
        $this->assertEquals(4, $stats['items_count']);
        $this->assertEquals(1, $stats['items_in_use']);
        $this->assertEquals(1, $stats['items_unused']);
        $this->assertEquals(1, $stats['items_pre_arrival']);
        $this->assertEquals(1, $stats['items_discarded']);

        $this->assertIsArray($result['products']);
        $this->assertCount(2, $result['products']);

        $product1Data = $result['products'][0];
        $this->assertEquals(1, $product1Data['id']);
        $this->assertEquals('PRD001', $product1Data['short_id']);
        $this->assertEquals('產品1', $product1Data['name']);
        $this->assertEquals('品牌1', $product1Data['brand']);
        $this->assertEquals(2, $product1Data['items_count']);
        $this->assertArrayHasKey('status_counts', $product1Data);
    }

    public function test_get_category_with_stats_should_handle_category_with_no_products(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;
        $category->name = '測試分類';

        $this->mockRepository
            ->shouldReceive('getCategoryWithRelations')
            ->once()
            ->with($category, 1, 10)
            ->andReturn([
                'category' => $category,
                'products' => Collection::make([]),
                'all_products' => Collection::make([]),
                'all_items' => Collection::make([]),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                ],
            ]);

        $result = $this->service->getCategoryWithStats($category);

        $stats = $result['stats'];
        $this->assertEquals(0, $stats['products_count']);
        $this->assertEquals(0, $stats['items_count']);
        $this->assertEquals(0, $stats['items_in_use']);
        $this->assertEquals(0, $stats['items_unused']);
        $this->assertEquals(0, $stats['items_pre_arrival']);
        $this->assertEquals(0, $stats['items_discarded']);

        $this->assertIsArray($result['products']);
        $this->assertCount(0, $result['products']);
    }

    public function test_get_category_with_stats_should_handle_category_with_no_items(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;
        $category->name = '測試分類';

        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->id = 1;
        $product1->short_id = 'PRD001';
        $product1->name = '產品1';
        $product1->brand = '品牌1';
        $product1->items = Collection::make([]);

        $allProducts = Collection::make([$product1]);
        $products = Collection::make([$product1]);

        $this->mockRepository
            ->shouldReceive('getCategoryWithRelations')
            ->once()
            ->with($category, 1, 10)
            ->andReturn([
                'category' => $category,
                'products' => $products,
                'all_products' => $allProducts,
                'all_items' => Collection::make([]),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 1,
                ],
            ]);

        $result = $this->service->getCategoryWithStats($category);

        $stats = $result['stats'];
        $this->assertEquals(1, $stats['products_count']);
        $this->assertEquals(0, $stats['items_count']);
        $this->assertEquals(0, $stats['items_in_use']);
        $this->assertEquals(0, $stats['items_unused']);
        $this->assertEquals(0, $stats['items_pre_arrival']);
        $this->assertEquals(0, $stats['items_discarded']);

        $product1Data = $result['products'][0];
        $this->assertEquals(0, $product1Data['items_count']);
        $this->assertEquals(0, $product1Data['status_counts']['in_use']);
        $this->assertEquals(0, $product1Data['status_counts']['unused']);
        $this->assertEquals(0, $product1Data['status_counts']['pre_arrival']);
        $this->assertEquals(0, $product1Data['status_counts']['discarded']);
    }

    public function test_get_category_with_stats_should_calculate_status_counts_correctly(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->short_id = 'PRD001';
        $product->name = '產品1';
        $product->brand = '品牌1';

        $itemInUse = Mockery::mock(Item::class)->makePartial();
        $itemInUse->discarded_at = null;
        $itemInUse->used_at = now();
        $itemInUse->received_at = now()->subDays(10);

        $itemUnused = Mockery::mock(Item::class)->makePartial();
        $itemUnused->discarded_at = null;
        $itemUnused->used_at = null;
        $itemUnused->received_at = now()->subDays(5);

        $itemPreArrival = Mockery::mock(Item::class)->makePartial();
        $itemPreArrival->discarded_at = null;
        $itemPreArrival->used_at = null;
        $itemPreArrival->received_at = null;

        $itemUsedDiscarded = Mockery::mock(Item::class)->makePartial();
        $itemUsedDiscarded->discarded_at = now();
        $itemUsedDiscarded->used_at = now()->subDays(1);
        $itemUsedDiscarded->received_at = now()->subDays(10);

        $itemUnusedDiscarded = Mockery::mock(Item::class)->makePartial();
        $itemUnusedDiscarded->discarded_at = now();
        $itemUnusedDiscarded->used_at = null;
        $itemUnusedDiscarded->received_at = now()->subDays(5);

        $product->items = Collection::make([
            $itemInUse,
            $itemUnused,
            $itemPreArrival,
            $itemUsedDiscarded,
            $itemUnusedDiscarded,
        ]);

        $allProducts = Collection::make([$product]);
        $products = Collection::make([$product]);
        $allItems = Collection::make([
            $itemInUse,
            $itemUnused,
            $itemPreArrival,
            $itemUsedDiscarded,
            $itemUnusedDiscarded,
        ]);

        $this->mockRepository
            ->shouldReceive('getCategoryWithRelations')
            ->once()
            ->with($category, 1, 10)
            ->andReturn([
                'category' => $category,
                'products' => $products,
                'all_products' => $allProducts,
                'all_items' => $allItems,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 1,
                ],
            ]);

        $result = $this->service->getCategoryWithStats($category);

        $stats = $result['stats'];
        $this->assertEquals(1, $stats['items_in_use']);
        $this->assertEquals(1, $stats['items_unused']);
        $this->assertEquals(1, $stats['items_pre_arrival']);
        $this->assertEquals(2, $stats['items_discarded']);

        $productData = $result['products'][0];
        $this->assertEquals(1, $productData['status_counts']['in_use']);
        $this->assertEquals(1, $productData['status_counts']['unused']);
        $this->assertEquals(1, $productData['status_counts']['pre_arrival']);
        $this->assertEquals(2, $productData['status_counts']['discarded']);
    }

    public function test_get_category_with_stats_should_paginate_products_correctly(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;

        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->id = 1;
        $product1->short_id = 'PRD001';
        $product1->name = '產品1';
        $product1->brand = '品牌1';
        $product1->items = Collection::make([]);

        $product2 = Mockery::mock(Product::class)->makePartial();
        $product2->id = 2;
        $product2->short_id = 'PRD002';
        $product2->name = '產品2';
        $product2->brand = '品牌2';
        $product2->items = Collection::make([]);

        $allProducts = Collection::make([$product1, $product2]);
        $products = Collection::make([$product1]);

        $this->mockRepository
            ->shouldReceive('getCategoryWithRelations')
            ->once()
            ->with($category, 1, 1)
            ->andReturn([
                'category' => $category,
                'products' => $products,
                'all_products' => $allProducts,
                'all_items' => Collection::make([]),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 2,
                    'per_page' => 1,
                    'total' => 2,
                ],
            ]);

        $result = $this->service->getCategoryWithStats($category, 1, 1);

        $this->assertCount(1, $result['products']);
        $this->assertEquals(1, $result['meta']['current_page']);
        $this->assertEquals(2, $result['meta']['last_page']);
        $this->assertEquals(1, $result['meta']['per_page']);
        $this->assertEquals(2, $result['meta']['total']);
    }
}
