<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CategoryService;
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
}
