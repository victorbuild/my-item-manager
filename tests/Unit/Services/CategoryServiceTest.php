<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    /**
     * @var \Mockery\MockInterface&CategoryRepository
     */
    private $mockRepository;

    private CategoryService $service;

    private int $testUserId = 1;
    private array $testCategoryData = ['name' => '測試分類'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepository = Mockery::mock(CategoryRepository::class);
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

        $mockHasMany = Mockery::mock(HasMany::class);
        $mockHasMany->shouldReceive('where')
            ->with('user_id', $this->testUserId)
            ->once()
            ->andReturnSelf();
        $mockHasMany->shouldReceive('count')
            ->once()
            ->andReturn(0);

        $category->shouldReceive('products')
            ->once()
            ->andReturn($mockHasMany);

        $this->mockRepository
            ->shouldReceive('findOrFail')
            ->once()
            ->with(1, $this->testUserId)
            ->andReturn($category);

        $this->mockRepository
            ->shouldReceive('delete')
            ->once()
            ->with($category)
            ->andReturn(true);

        $result = $this->service->delete(1, $this->testUserId);

        $this->assertTrue($result);
    }

    public function test_delete_should_throw_exception_when_category_has_products(): void
    {
        $category = Mockery::mock(Category::class)->makePartial();
        $category->id = 1;
        $category->user_id = $this->testUserId;

        $mockHasMany = Mockery::mock(HasMany::class);
        $mockHasMany->shouldReceive('where')
            ->with('user_id', $this->testUserId)
            ->once()
            ->andReturnSelf();
        $mockHasMany->shouldReceive('count')
            ->once()
            ->andReturn(3);

        $category->shouldReceive('products')
            ->once()
            ->andReturn($mockHasMany);

        $this->mockRepository
            ->shouldReceive('findOrFail')
            ->once()
            ->with(1, $this->testUserId)
            ->andReturn($category);

        $this->mockRepository
            ->shouldReceive('delete')
            ->never();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('無法刪除此分類，因為還有 3 個產品關聯此分類。');

        $this->service->delete(1, $this->testUserId);
    }

    public function test_getAll_should_return_collection_when_user_exists(): void
    {
        $expectedCategories = Collection::make([
            new Category(['id' => 1, 'name' => '分類1']),
            new Category(['id' => 2, 'name' => '分類2']),
        ]);

        $this->mockRepository
            ->shouldReceive('getAll')
            ->once()
            ->with($this->testUserId)
            ->andReturn($expectedCategories);

        $result = $this->service->getAll($this->testUserId);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function test_findOrFail_should_throw_exception_when_category_not_found(): void
    {
        $this->mockRepository
            ->shouldReceive('findOrFail')
            ->once()
            ->with(999, $this->testUserId)
            ->andThrow(new ModelNotFoundException());

        $this->expectException(ModelNotFoundException::class);

        $this->service->findOrFail(999, $this->testUserId);
    }

    public function test_update_should_return_updated_category_when_valid_data_provided(): void
    {
        // Arrange：準備分類和更新資料
        $category = new Category();
        $category->id = 1;
        $category->name = '舊名稱';
        $category->user_id = $this->testUserId;

        $updatedCategory = new Category();
        $updatedCategory->id = 1;
        $updatedCategory->name = '新名稱';
        $updatedCategory->user_id = $this->testUserId;

        $updateData = ['name' => '新名稱'];

        $this->mockRepository
            ->shouldReceive('findOrFail')
            ->once()
            ->with(1, $this->testUserId)
            ->andReturn($category);

        $this->mockRepository
            ->shouldReceive('update')
            ->once()
            ->with($category, $updateData)
            ->andReturn($updatedCategory);

        $result = $this->service->update(1, $updateData, $this->testUserId);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('新名稱', $result->name);
    }
}
