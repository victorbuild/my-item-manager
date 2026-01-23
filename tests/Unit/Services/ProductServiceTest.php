<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ProductService 單元測試
 *
 * 透過 mock ProductRepository，隔離 Service 邏輯，驗證：
 * - deleteIfNoItems 正確呼叫 Repository
 * - 正確傳遞 Repository 的返回值
 */
class ProductServiceTest extends TestCase
{
    private ProductService $productService;

    /**
     * @var \Mockery\MockInterface&ProductRepository
     */
    private $mockProductRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock ProductRepository（使用 Mockery，與專案其他測試一致）
        $this->mockProductRepository = Mockery::mock(ProductRepository::class);

        // 建立 ProductService 實例（注入 mock repository）
        $this->productService = new ProductService($this->mockProductRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Product 有 Items 時，應返回 false（不刪除）
     */
    #[Test]
    public function it_should_return_false_when_product_has_items(): void
    {
        // 建立 Product 實例（不依賴資料庫，這是 Unit Test）
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';

        $this->mockProductRepository
            ->shouldReceive('deleteIfNoItems')
            ->once()
            ->with($product)
            ->andReturn(false);

        $result = $this->productService->deleteIfNoItems($product);

        $this->assertFalse($result);
    }

    /**
     * Product 沒有 Items 時，應返回 true（成功刪除）
     */
    #[Test]
    public function it_should_return_true_when_product_has_no_items(): void
    {
        // 建立 Product 實例（不依賴資料庫，這是 Unit Test）
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';

        $this->mockProductRepository
            ->shouldReceive('deleteIfNoItems')
            ->once()
            ->with($product)
            ->andReturn(true);

        $result = $this->productService->deleteIfNoItems($product);

        $this->assertTrue($result);
    }

    /**
     * Repository 刪除失敗時，應正確傳遞 false
     */
    #[Test]
    public function it_should_propagate_false_when_repository_delete_fails(): void
    {
        // 建立 Product 實例（不依賴資料庫，這是 Unit Test）
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';

        $this->mockProductRepository
            ->shouldReceive('deleteIfNoItems')
            ->once()
            ->with($product)
            ->andReturn(false);

        $result = $this->productService->deleteIfNoItems($product);

        $this->assertFalse($result);
    }
}
