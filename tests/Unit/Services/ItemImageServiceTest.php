<?php

namespace Tests\Unit\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Repositories\Contracts\ItemImageRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Services\ItemImageService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemImageServiceTest extends TestCase
{
    /**
     * @var \Mockery\MockInterface&ItemImageRepositoryInterface
     */
    private $mockRepository;

    /**
     * @var \Mockery\MockInterface&ItemRepositoryInterface
     */
    private $mockItemRepository;

    private ItemImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepository = Mockery::mock(ItemImageRepositoryInterface::class);
        $this->mockItemRepository = Mockery::mock(ItemRepositoryInterface::class);
        // 階段 2：使用 Mock ItemRepository（真正的 Unit 測試）
        $this->service = new ItemImageService(
            $this->mockRepository,
            $this->mockItemRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 測試：空陣列時不處理
     */
    #[Test]
    public function it_should_do_nothing_when_images_array_is_empty(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;

        // Act
        $this->service->attachImagesToItem($item, []);

        // Assert - 驗證行為：空陣列時不應該調用任何方法
        $this->mockRepository->shouldNotHaveReceived('findByUuid');
        $this->mockItemRepository->shouldNotHaveReceived('attachImage');

        // 明確的 assertion：驗證方法正常執行（沒有拋出異常）
        $this->assertTrue(true, '空陣列時方法應該正常返回，不拋出異常');
    }

    /**
     * 測試：成功附加圖片並更新使用次數
     */
    #[Test]
    public function it_should_attach_images_and_increment_usage_when_valid_images_provided(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->status = ItemImage::STATUS_DRAFT;
        $image2 = new ItemImage();
        $image2->uuid = 'uuid-2';
        $image2->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'],
            ['uuid' => 'uuid-2', 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-2')
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with(Mockery::on(function ($img) {
                return $img->uuid === 'uuid-1' || $img->uuid === 'uuid-2';
            }))
            ->twice();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) {
                return ($img->uuid === 'uuid-1' || $img->uuid === 'uuid-2')
                    && $img->status === ItemImage::STATUS_DRAFT;
            }), ItemImage::STATUS_USED)
            ->twice();

        // Mock ItemRepository attachImage - 驗證 sort_order 正確遞增
        $sortOrderCallCount = 0;
        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, Mockery::on(function ($uuid) {
                return $uuid === 'uuid-1' || $uuid === 'uuid-2';
            }), Mockery::on(function ($pivotData) use (&$sortOrderCallCount) {
                $sortOrderCallCount++;

                return isset($pivotData['sort_order'])
                    && $pivotData['sort_order'] === $sortOrderCallCount
                    && isset($pivotData['created_at'])
                    && isset($pivotData['updated_at']);
            }))
            ->twice();

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert - 驗證行為：attachImage 被調用 2 次，sort_order 正確遞增
        $this->assertEquals(2, $sortOrderCallCount, 'attachImage 應該被調用 2 次');
    }

    /**
     * 測試：只處理 status='new' 的圖片
     */
    #[Test]
    public function it_should_only_process_images_with_status_new(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->status = ItemImage::STATUS_DRAFT;
        $image2 = new ItemImage();
        $image2->uuid = 'uuid-2';
        $image2->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'],      // 應該處理
            ['uuid' => 'uuid-2', 'status' => 'original'], // 應該跳過
        ];

        // Mock Repository 行為（只應該被呼叫一次）
        $findByUuidCallCount = 0;
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturnUsing(function ($uuid) use (&$findByUuidCallCount, $image1) {
                $findByUuidCallCount++;

                return $image1;
            });

        $incrementUsageCountCallCount = 0;
        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->once()
            ->andReturnUsing(function () use (&$incrementUsageCountCallCount) {
                $incrementUsageCountCallCount++;
            });

        $updateStatusCallCount = 0;
        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->andReturnUsing(function () use (&$updateStatusCallCount) {
                $updateStatusCallCount++;
            });

        // Mock ItemRepository attachImage（只應該被呼叫一次）
        $attachCallCount = 0;
        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, 'uuid-1', Mockery::type('array'))
            ->once()
            ->andReturnUsing(function () use (&$attachCallCount) {
                $attachCallCount++;
            });

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert - 驗證行為：只處理 status='new' 的圖片，status='original' 的圖片應該被跳過
        $this->assertEquals(1, $findByUuidCallCount, 'findByUuid 應該只被調用 1 次（只處理 uuid-1）');
        $this->assertEquals(1, $incrementUsageCountCallCount, 'incrementUsageCount 應該只被調用 1 次');
        $this->assertEquals(1, $updateStatusCallCount, 'updateStatus 應該只被調用 1 次');
        $this->assertEquals(1, $attachCallCount, 'attachImage 應該只被調用 1 次（只處理 uuid-1）');
    }

    /**
     * 測試：跳過無 UUID 的圖片
     */
    #[Test]
    public function it_should_skip_images_without_uuid(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'], // 應該處理
            ['status' => 'new'],                     // 應該跳過（無 UUID）
        ];

        // Mock Repository 行為（只應該被呼叫一次）
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->once();

        // Mock ItemRepository attachImage（只應該被呼叫一次，因為第二個圖片沒有 UUID）
        $attachCallCount = 0;
        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, 'uuid-1', Mockery::type('array'))
            ->once()
            ->andReturnUsing(function () use (&$attachCallCount) {
                $attachCallCount++;
            });

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert - 驗證行為：只處理有 UUID 的圖片，沒有 UUID 的圖片應該被跳過
        $this->assertEquals(1, $attachCallCount, '只有有 UUID 的圖片應該被處理');
    }

    /**
     * 測試：圖片狀態為 used 時不更新狀態
     */
    #[Test]
    public function it_should_not_update_status_when_image_is_already_used(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image = new ItemImage();
        $image->uuid = 'uuid-1';
        $image->status = ItemImage::STATUS_USED;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturn($image);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with($image)
            ->once();

        // 不應該呼叫 updateStatus（因為狀態已經是 used）
        $this->mockRepository
            ->shouldNotReceive('updateStatus');

        // Mock ItemRepository attachImage
        $attachCallCount = 0;
        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, 'uuid-1', Mockery::type('array'))
            ->once()
            ->andReturnUsing(function () use (&$attachCallCount) {
                $attachCallCount++;
            });

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert - 驗證行為：attachImage 被調用，但 updateStatus 不被調用（因為狀態已經是 used）
        $this->assertEquals(1, $attachCallCount, 'attachImage 應該被調用 1 次');
    }

    /**
     * 測試：sort_order 正確遞增
     */
    #[Test]
    public function it_should_set_correct_sort_order(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->status = ItemImage::STATUS_DRAFT;
        $image2 = new ItemImage();
        $image2->uuid = 'uuid-2';
        $image2->status = ItemImage::STATUS_DRAFT;
        $image3 = new ItemImage();
        $image3->uuid = 'uuid-3';
        $image3->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'],
            ['uuid' => 'uuid-2', 'status' => 'new'],
            ['uuid' => 'uuid-3', 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->andReturnUsing(function ($uuid) use ($image1, $image2, $image3) {
                if ($uuid === 'uuid-1') {
                    return $image1;
                }
                if ($uuid === 'uuid-2') {
                    return $image2;
                }
                if ($uuid === 'uuid-3') {
                    return $image3;
                }

                return null;
            });

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->times(3);

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->times(3);

        // Mock ItemRepository attachImage - 驗證 sort_order 正確遞增（1, 2, 3）
        $sortOrderCallCount = 0;
        $sortOrders = [];
        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, Mockery::on(function ($uuid) {
                return $uuid === 'uuid-1' || $uuid === 'uuid-2' || $uuid === 'uuid-3';
            }), Mockery::on(function ($pivotData) use (&$sortOrderCallCount, &$sortOrders) {
                $sortOrderCallCount++;
                $sortOrders[] = $pivotData['sort_order'];

                return isset($pivotData['sort_order'])
                    && $pivotData['sort_order'] === $sortOrderCallCount
                    && isset($pivotData['created_at'])
                    && isset($pivotData['updated_at']);
            }))
            ->times(3);

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert - 驗證行為：attachImage 被調用 3 次，sort_order 正確遞增為 1, 2, 3
        $this->assertEquals(3, $sortOrderCallCount, 'attachImage 應該被調用 3 次');
        $this->assertEquals([1, 2, 3], $sortOrders, 'sort_order 應該正確遞增為 1, 2, 3');
    }

    /**
     * 測試：圖片不存在時仍會 attach，但不會更新使用次數
     */
    #[Test]
    public function it_should_attach_image_even_when_image_not_found_but_not_update_usage(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'],
        ];

        // Mock Repository 行為（返回 null，模擬圖片不存在的情況）
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturn(null);

        // 不應該呼叫 incrementUsageCount 或 updateStatus
        $this->mockRepository
            ->shouldNotReceive('incrementUsageCount');

        $this->mockRepository
            ->shouldNotReceive('updateStatus');

        // Mock ItemRepository attachImage（即使圖片不存在，attach 仍會被調用）
        $attachCallCount = 0;
        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, 'uuid-1', Mockery::type('array'))
            ->once()
            ->andReturnUsing(function () use (&$attachCallCount) {
                $attachCallCount++;
            });

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert - 驗證行為：即使圖片不存在（findByUuid 返回 null），attachImage 仍會被調用
        // 但 incrementUsageCount 和 updateStatus 不會被調用
        $this->assertEquals(1, $attachCallCount, '即使圖片不存在，attachImage 仍應該被調用');
    }

    /**
     * 測試：驗證圖片數量 - 符合規範
     */
    #[Test]
    public function it_should_validate_image_count_correctly(): void
    {
        // Arrange
        $images = [
            ['uuid' => 'uuid1', 'status' => 'new'],
            ['uuid' => 'uuid2', 'status' => 'original'],
            ['uuid' => 'uuid3', 'status' => 'removed'], // 不計算
        ];

        // Act
        $result = $this->service->validateImageCount($images, 9);

        // Assert
        $this->assertTrue($result); // 只有 2 張（new + original），小於等於 9
    }

    /**
     * 測試：驗證圖片數量 - 超過限制
     */
    #[Test]
    public function it_should_return_false_when_image_count_exceeds_limit(): void
    {
        // Arrange
        $images = [];
        for ($i = 1; $i <= 10; $i++) {
            $images[] = ['uuid' => "uuid{$i}", 'status' => 'new'];
        }

        // Act
        $result = $this->service->validateImageCount($images, 9);

        // Assert
        $this->assertFalse($result); // 10 張超過 9 張限制
    }

    /**
     * 測試：驗證圖片數量 - 空陣列
     */
    #[Test]
    public function it_should_return_true_when_images_array_is_empty(): void
    {
        // Act
        $result = $this->service->validateImageCount([], 9);

        // Assert
        $this->assertTrue($result); // 0 張符合限制
    }

    /**
     * 測試：同步圖片 - 空陣列時不處理
     */
    #[Test]
    public function it_should_do_nothing_when_sync_images_array_is_empty(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;

        // Mock ItemRepository refreshWithRelations
        $this->mockItemRepository
            ->shouldReceive('refreshWithRelations')
            ->with($item)
            ->once()
            ->andReturn($item);

        // Act
        $result = $this->service->syncItemImages($item, []);

        // Assert - 驗證行為：空陣列時應該返回 refreshWithRelations 的結果，不應該調用 findByUuid
        $this->assertSame($item, $result, '空陣列時應該返回 refreshWithRelations 的結果');
        $this->mockRepository->shouldNotHaveReceived('findByUuid');
    }

    /**
     * 測試：同步圖片 - 移除圖片
     */
    #[Test]
    public function it_should_remove_images_when_status_is_removed(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        // 建立 usage_count = 1 的圖片，decrement 後會變成 0
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->usage_count = 1;
        $image2 = new ItemImage();
        $image2->uuid = 'uuid-2';
        $image2->usage_count = 2;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'removed'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('decrementUsageCount')
            ->with($image1)
            ->once();

        // decrement 後，usage_count 會變成 0（1 - 1 = 0）
        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) {
                return $img->uuid === 'uuid-1' && $img->usage_count <= 0;
            }), ItemImage::STATUS_DRAFT)
            ->once();

        // Mock ItemRepository detachImage 和 refreshWithRelations
        $this->mockItemRepository
            ->shouldReceive('detachImage')
            ->with($item, 'uuid-1')
            ->once();

        $this->mockItemRepository
            ->shouldReceive('refreshWithRelations')
            ->with($item)
            ->once()
            ->andReturn($item);

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert - 驗證行為：detachImage 和 refreshWithRelations 被正確調用
        $this->assertTrue(true, 'syncItemImages 應該正常執行，移除圖片並重新載入關聯');
    }

    /**
     * 測試：同步圖片 - 新增圖片
     */
    #[Test]
    public function it_should_add_images_when_status_is_new(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->status = ItemImage::STATUS_DRAFT;
        $image2 = new ItemImage();
        $image2->uuid = 'uuid-2';
        $image2->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'],
            ['uuid' => 'uuid-2', 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-2')
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with(Mockery::on(function ($img) {
                return $img->uuid === 'uuid-1' || $img->uuid === 'uuid-2';
            }))
            ->twice();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) {
                return ($img->uuid === 'uuid-1' || $img->uuid === 'uuid-2')
                    && $img->status === ItemImage::STATUS_DRAFT;
            }), ItemImage::STATUS_USED)
            ->twice();

        // Mock ItemRepository hasImage, attachImage, refreshWithRelations
        $this->mockItemRepository
            ->shouldReceive('hasImage')
            ->with($item, Mockery::on(function ($uuid) {
                return $uuid === 'uuid-1' || $uuid === 'uuid-2';
            }))
            ->twice()
            ->andReturn(false);

        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, Mockery::on(function ($uuid) {
                return $uuid === 'uuid-1' || $uuid === 'uuid-2';
            }), Mockery::type('array'))
            ->twice();

        $this->mockItemRepository
            ->shouldReceive('refreshWithRelations')
            ->with($item)
            ->once()
            ->andReturn($item);

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert - 驗證行為：hasImage, attachImage, refreshWithRelations 被正確調用
        $this->assertTrue(true, 'syncItemImages 應該正常執行，新增圖片並重新載入關聯');
    }

    /**
     * 測試：同步圖片 - 原始圖片不異動
     */
    #[Test]
    public function it_should_not_modify_images_with_status_original(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->status = ItemImage::STATUS_DRAFT;
        $image2 = new ItemImage();
        $image2->uuid = 'uuid-2';
        $image2->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'original'], // 不異動
            ['uuid' => 'uuid-2', 'status' => 'new'],      // 新增
        ];

        // Mock Repository 行為（只處理 new 的圖片）
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-2')
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with($image2)
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->once();

        // Mock ItemRepository hasImage, attachImage, refreshWithRelations
        $this->mockItemRepository
            ->shouldReceive('hasImage')
            ->with($item, 'uuid-2')
            ->once()
            ->andReturn(false);

        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, 'uuid-2', Mockery::type('array'))
            ->once();

        $this->mockItemRepository
            ->shouldReceive('refreshWithRelations')
            ->with($item)
            ->once()
            ->andReturn($item);

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert - 驗證行為：只處理 status='new' 的圖片，status='original' 的圖片不異動
        $this->assertTrue(true, 'syncItemImages 應該正常執行，只處理 status=new 的圖片');
    }

    /**
     * 測試：同步圖片 - 同時新增和移除
     */
    #[Test]
    public function it_should_handle_both_add_and_remove_in_same_sync(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image1 = new ItemImage();
        $image1->uuid = 'uuid-1';
        $image1->usage_count = 1;
        $image2 = new ItemImage();
        $image2->uuid = 'uuid-2';
        $image2->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'removed'], // 移除
            ['uuid' => 'uuid-2', 'status' => 'new'],      // 新增
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-1')
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with('uuid-2')
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('decrementUsageCount')
            ->with($image1)
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) {
                return $img->uuid === 'uuid-1' && $img->usage_count <= 0;
            }), ItemImage::STATUS_DRAFT)
            ->once();

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with($image2)
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) {
                return $img->uuid === 'uuid-2' && $img->status === ItemImage::STATUS_DRAFT;
            }), ItemImage::STATUS_USED)
            ->once();

        // Mock ItemRepository detachImage, hasImage, attachImage, refreshWithRelations
        $this->mockItemRepository
            ->shouldReceive('detachImage')
            ->with($item, 'uuid-1')
            ->once();

        $this->mockItemRepository
            ->shouldReceive('hasImage')
            ->with($item, 'uuid-2')
            ->once()
            ->andReturn(false);

        $this->mockItemRepository
            ->shouldReceive('attachImage')
            ->with($item, 'uuid-2', Mockery::type('array'))
            ->once();

        $this->mockItemRepository
            ->shouldReceive('refreshWithRelations')
            ->with($item)
            ->once()
            ->andReturn($item);

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert - 驗證行為：同時處理移除和新增，detachImage, hasImage, attachImage, refreshWithRelations 都被正確調用
        $this->assertTrue(true, 'syncItemImages 應該正常執行，同時處理移除和新增');
    }

    /**
     * 測試：同步圖片 - 避免重複 attach
     */
    #[Test]
    public function it_should_not_attach_duplicate_images(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $image = new ItemImage();
        $image->uuid = 'uuid-1';
        $image->status = ItemImage::STATUS_DRAFT;

        $images = [
            ['uuid' => 'uuid-1', 'status' => 'new'], // 嘗試再次附加
        ];

        // Mock Repository 行為
        // 由於不會 attach（因為已經存在），所以不應該呼叫 incrementUsageCount
        $this->mockRepository
            ->shouldNotReceive('findByUuid');

        $this->mockRepository
            ->shouldNotReceive('incrementUsageCount');

        $this->mockRepository
            ->shouldNotReceive('updateStatus');

        // Mock ItemRepository hasImage（返回 true，表示圖片已存在）
        $this->mockItemRepository
            ->shouldReceive('hasImage')
            ->with($item, 'uuid-1')
            ->once()
            ->andReturn(true);

        // 不應該呼叫 attachImage（因為圖片已存在）
        $this->mockItemRepository
            ->shouldNotReceive('attachImage');

        $this->mockItemRepository
            ->shouldReceive('refreshWithRelations')
            ->with($item)
            ->once()
            ->andReturn($item);

        // Act
        $result = $this->service->syncItemImages($item, $images);

        // Assert - 驗證行為：hasImage 返回 true 時，不應該調用 attachImage（避免重複 attach）
        $this->assertSame($item, $result, 'syncItemImages 應該返回 refreshWithRelations 的結果');
    }

    /**
     * 測試：成功上傳圖片
     */
    #[Test]
    public function it_should_upload_image_successfully(): void
    {
        // Arrange
        $userId = 1;
        $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg', 800, 600);

        // Mock Storage
        \Illuminate\Support\Facades\Storage::fake('gcs');

        // Mock Repository
        $itemImage = ItemImage::factory()->make([
            'uuid' => 'test-uuid',
            'user_id' => $userId,
            'status' => ItemImage::STATUS_DRAFT,
        ]);
        $itemImage->id = 1; // 設定 ID 以便後續使用

        $this->mockRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($userId) {
                return isset($data['uuid'])
                    && isset($data['image_path'])
                    && isset($data['original_extension'])
                    && $data['status'] === ItemImage::STATUS_DRAFT
                    && $data['usage_count'] === 0
                    && $data['user_id'] === $userId;
            }))
            ->andReturn($itemImage);

        // Act
        $result = $this->service->uploadImage($file, $userId);

        // Assert
        $this->assertInstanceOf(ItemImage::class, $result);
        $this->assertEquals($userId, $result->user_id);
        $this->assertEquals(ItemImage::STATUS_DRAFT, $result->status);

        // 驗證檔案已上傳（檢查 Storage 中是否有檔案）
        $files = \Illuminate\Support\Facades\Storage::disk('gcs')->allFiles('item-images');
        $this->assertNotEmpty($files, '應該有檔案被上傳');
        $this->assertCount(3, $files, '應該有 3 個檔案（原圖、預覽圖、縮圖）');

        // 驗證檔案名稱格式
        $hasOriginal = false;
        $hasPreview = false;
        $hasThumb = false;
        foreach ($files as $filePath) {
            if (str_contains($filePath, 'original_')) {
                $hasOriginal = true;
            }
            if (str_contains($filePath, 'preview_')) {
                $hasPreview = true;
            }
            if (str_contains($filePath, 'thumb_')) {
                $hasThumb = true;
            }
        }
        $this->assertTrue($hasOriginal, '應該有原圖檔案');
        $this->assertTrue($hasPreview, '應該有預覽圖檔案');
        $this->assertTrue($hasThumb, '應該有縮圖檔案');
    }

    /**
     * 測試：檔案讀取失敗時拋出異常
     */
    #[Test]
    public function it_should_throw_exception_when_file_read_fails(): void
    {
        // Arrange
        $userId = 1;
        // 建立一個不存在的檔案路徑
        $file = Mockery::mock(\Illuminate\Http\UploadedFile::class);
        $file->shouldReceive('getRealPath')
            ->andReturn('/tmp/non-existent-file-' . uniqid() . '.jpg');
        $file->shouldReceive('getClientOriginalExtension')
            ->andReturn('jpg');

        // Act & Assert
        // Service 層會捕獲 file_get_contents 拋出的異常並轉換為 HttpException
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('無法讀取檔案內容');

        $this->service->uploadImage($file, $userId);
    }

    /**
     * 測試：原圖上傳失敗時拋出異常
     */
    #[Test]
    public function it_should_throw_exception_when_original_upload_fails(): void
    {
        // Arrange
        $userId = 1;
        $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg', 800, 600);

        // Mock Storage 讓 put 返回 false，並允許 delete 被呼叫（catch 區塊會清理）
        $storageMock = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $storageMock->shouldReceive('put')
            ->andReturn(false); // 模擬上傳失敗
        $storageMock->shouldReceive('delete')
            ->andReturn(true); // catch 區塊會嘗試清理

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('gcs')
            ->andReturn($storageMock);

        // Act & Assert
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('原圖上傳失敗');

        $this->service->uploadImage($file, $userId);
    }

    /**
     * 測試：縮圖上傳失敗時清理已上傳的檔案
     */
    #[Test]
    public function it_should_cleanup_files_when_thumbnail_upload_fails(): void
    {
        // Arrange
        $userId = 1;
        $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg', 800, 600);

        // Mock Storage：原圖上傳成功，預覽圖上傳成功，縮圖上傳失敗
        $putCallCount = 0;
        $deleteCalled = false;
        $deletePaths = [];

        $storageMock = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $storageMock->shouldReceive('put')
            ->andReturnUsing(function ($path, $content) use (&$putCallCount) {
                $putCallCount++;

                // 前兩次（原圖、預覽圖）成功，第三次（縮圖）失敗
                return $putCallCount < 3;
            });
        $storageMock->shouldReceive('delete')
            ->andReturnUsing(function ($paths) use (&$deleteCalled, &$deletePaths) {
                $deleteCalled = true;
                $deletePaths = is_array($paths) ? $paths : [$paths];

                return true;
            });

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('gcs')
            ->andReturn($storageMock);

        // Act & Assert
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('縮圖上傳失敗');

        try {
            $this->service->uploadImage($file, $userId);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // 驗證異常被拋出
            $this->assertEquals('縮圖上傳失敗', $e->getMessage());
            // 驗證清理被呼叫
            $this->assertTrue($deleteCalled, '應該呼叫 delete 清理檔案');
            $this->assertNotEmpty($deletePaths, '應該有檔案路徑被傳入 delete');
            throw $e;
        }
    }

    /**
     * 測試：資料庫寫入失敗時清理已上傳的檔案
     */
    #[Test]
    public function it_should_cleanup_files_when_database_write_fails(): void
    {
        // Arrange
        $userId = 1;
        $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg', 800, 600);

        // Mock Storage：所有上傳都成功
        $deleteCalled = false;
        $deletePaths = [];

        $storageMock = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $storageMock->shouldReceive('put')
            ->andReturn(true); // 所有上傳都成功
        $storageMock->shouldReceive('delete')
            ->andReturnUsing(function ($paths) use (&$deleteCalled, &$deletePaths) {
                $deleteCalled = true;
                $deletePaths = is_array($paths) ? $paths : [$paths];

                return true;
            });

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('gcs')
            ->andReturn($storageMock);

        // Mock Repository 拋出異常（模擬資料庫寫入失敗）
        $this->mockRepository
            ->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Database write failed'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database write failed');

        try {
            $this->service->uploadImage($file, $userId);
        } catch (\Exception $e) {
            // 驗證異常被拋出
            $this->assertEquals('Database write failed', $e->getMessage());
            // 驗證清理被呼叫（catch 區塊會清理檔案）
            $this->assertTrue($deleteCalled, '應該呼叫 delete 清理檔案');
            $this->assertNotEmpty($deletePaths, '應該有檔案路徑被傳入 delete');
            throw $e;
        }
    }
}
