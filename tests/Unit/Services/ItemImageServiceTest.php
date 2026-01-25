<?php

namespace Tests\Unit\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Repositories\Contracts\ItemImageRepositoryInterface;
use App\Services\ItemImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemImageServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var \Mockery\MockInterface&ItemImageRepositoryInterface
     */
    private $mockRepository;

    private ItemImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRepository = Mockery::mock(ItemImageRepositoryInterface::class);
        $this->service = new ItemImageService($this->mockRepository);
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
        $item = Item::factory()->create();

        // Act
        $this->service->attachImagesToItem($item, []);

        // Assert
        $this->assertEquals(0, $item->images()->count());
        $this->mockRepository->shouldNotHaveReceived('findByUuid');
    }

    /**
     * 測試：成功附加圖片並更新使用次數
     */
    #[Test]
    public function it_should_attach_images_and_increment_usage_when_valid_images_provided(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);
        $image2 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'new'],
            ['uuid' => $image2->uuid, 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image1->uuid)
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image2->uuid)
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with(Mockery::on(function ($img) use ($image1, $image2) {
                return $img->uuid === $image1->uuid || $img->uuid === $image2->uuid;
            }))
            ->twice();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) use ($image1, $image2) {
                return ($img->uuid === $image1->uuid || $img->uuid === $image2->uuid)
                    && $img->status === ItemImage::STATUS_DRAFT;
            }), ItemImage::STATUS_USED)
            ->twice();

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert
        $this->assertEquals(2, $item->images()->count());
        $this->assertTrue($item->images->contains('uuid', $image1->uuid));
        $this->assertTrue($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：只處理 status='new' 的圖片
     */
    #[Test]
    public function it_should_only_process_images_with_status_new(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create();
        $image2 = ItemImage::factory()->create();

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'new'],      // 應該處理
            ['uuid' => $image2->uuid, 'status' => 'original'], // 應該跳過
        ];

        // Mock Repository 行為（只應該被呼叫一次）
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image1->uuid)
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->once();

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert
        $this->assertEquals(1, $item->images()->count());
        $this->assertTrue($item->images->contains('uuid', $image1->uuid));
        $this->assertFalse($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：跳過無 UUID 的圖片
     */
    #[Test]
    public function it_should_skip_images_without_uuid(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create();

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'new'], // 應該處理
            ['status' => 'new'],                          // 應該跳過（無 UUID）
        ];

        // Mock Repository 行為（只應該被呼叫一次）
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image1->uuid)
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->once();

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert
        $this->assertEquals(1, $item->images()->count());
    }

    /**
     * 測試：圖片狀態從 draft 變為 used
     */
    #[Test]
    public function it_should_update_status_from_draft_to_used(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);

        $images = [
            ['uuid' => $image->uuid, 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image->uuid)
            ->once()
            ->andReturn($image);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with($image)
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) use ($image) {
                return $img->uuid === $image->uuid && $img->status === ItemImage::STATUS_DRAFT;
            }), ItemImage::STATUS_USED)
            ->once();

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert
        $this->assertEquals(1, $item->images()->count());
        $this->assertTrue($item->images->contains('uuid', $image->uuid));
    }

    /**
     * 測試：圖片狀態為 used 時不更新狀態
     */
    #[Test]
    public function it_should_not_update_status_when_image_is_already_used(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['status' => ItemImage::STATUS_USED]);

        $images = [
            ['uuid' => $image->uuid, 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image->uuid)
            ->once()
            ->andReturn($image);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with($image)
            ->once();

        // 不應該呼叫 updateStatus（因為狀態已經是 used）
        $this->mockRepository
            ->shouldNotReceive('updateStatus');

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert
        $this->assertEquals(1, $item->images()->count());
    }

    /**
     * 測試：sort_order 正確遞增
     */
    #[Test]
    public function it_should_set_correct_sort_order(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create();
        $image2 = ItemImage::factory()->create();
        $image3 = ItemImage::factory()->create();

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'new'],
            ['uuid' => $image2->uuid, 'status' => 'new'],
            ['uuid' => $image3->uuid, 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->andReturnUsing(function ($uuid) use ($image1, $image2, $image3) {
                if ($uuid === $image1->uuid) {
                    return $image1;
                }
                if ($uuid === $image2->uuid) {
                    return $image2;
                }
                if ($uuid === $image3->uuid) {
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

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert
        $attachedImages = $item->images()->orderBy('sort_order')->get();
        $this->assertEquals(1, $attachedImages[0]->pivot->sort_order);
        $this->assertEquals(2, $attachedImages[1]->pivot->sort_order);
        $this->assertEquals(3, $attachedImages[2]->pivot->sort_order);
    }

    /**
     * 測試：圖片不存在時不更新使用次數
     */
    #[Test]
    public function it_should_not_update_usage_when_image_not_found(): void
    {
        // Arrange
        $item = Item::factory()->create();
        // 使用真實存在的圖片，但 Mock Repository 返回 null（模擬查詢失敗的情況）
        $image = ItemImage::factory()->create();

        $images = [
            ['uuid' => $image->uuid, 'status' => 'new'],
        ];

        // Mock Repository 行為（返回 null，模擬圖片不存在的情況）
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image->uuid)
            ->once()
            ->andReturn(null);

        // 不應該呼叫 incrementUsageCount 或 updateStatus
        $this->mockRepository
            ->shouldNotReceive('incrementUsageCount');

        $this->mockRepository
            ->shouldNotReceive('updateStatus');

        // Act
        $this->service->attachImagesToItem($item, $images);

        // Assert
        // 圖片會被 attach（因為 attach 在檢查之前），但不會更新使用次數
        $this->assertEquals(1, $item->images()->count());
        // 驗證圖片確實被 attach
        $this->assertTrue($item->images->contains('uuid', $image->uuid));
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
        $item = Item::factory()->create();

        // Act
        $this->service->syncItemImages($item, []);

        // Assert
        $this->assertEquals(0, $item->images()->count());
        $this->mockRepository->shouldNotHaveReceived('findByUuid');
    }

    /**
     * 測試：同步圖片 - 移除圖片
     */
    #[Test]
    public function it_should_remove_images_when_status_is_removed(): void
    {
        // Arrange
        $item = Item::factory()->create();
        // 建立 usage_count = 1 的圖片，decrement 後會變成 0
        $image1 = ItemImage::factory()->create(['usage_count' => 1]);
        $image2 = ItemImage::factory()->create(['usage_count' => 2]);

        // 先附加圖片
        $item->images()->attach($image1->uuid, ['sort_order' => 1]);
        $item->images()->attach($image2->uuid, ['sort_order' => 2]);

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'removed'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image1->uuid)
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('decrementUsageCount')
            ->with($image1)
            ->once();

        // decrement 後，usage_count 會變成 0（1 - 1 = 0）
        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) use ($image1) {
                return $img->uuid === $image1->uuid && $img->usage_count <= 0;
            }), ItemImage::STATUS_DRAFT)
            ->once();

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert
        $this->assertEquals(1, $item->images()->count()); // 只剩 image2
        $this->assertFalse($item->images->contains('uuid', $image1->uuid));
        $this->assertTrue($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：同步圖片 - 新增圖片
     */
    #[Test]
    public function it_should_add_images_when_status_is_new(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);
        $image2 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'new'],
            ['uuid' => $image2->uuid, 'status' => 'new'],
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image1->uuid)
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image2->uuid)
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with(Mockery::on(function ($img) use ($image1, $image2) {
                return $img->uuid === $image1->uuid || $img->uuid === $image2->uuid;
            }))
            ->twice();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) use ($image1, $image2) {
                return ($img->uuid === $image1->uuid || $img->uuid === $image2->uuid)
                    && $img->status === ItemImage::STATUS_DRAFT;
            }), ItemImage::STATUS_USED)
            ->twice();

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert
        $this->assertEquals(2, $item->images()->count());
        $this->assertTrue($item->images->contains('uuid', $image1->uuid));
        $this->assertTrue($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：同步圖片 - 原始圖片不異動
     */
    #[Test]
    public function it_should_not_modify_images_with_status_original(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create();
        $image2 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);

        // 先附加圖片
        $item->images()->attach($image1->uuid, ['sort_order' => 1]);

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'original'], // 不異動
            ['uuid' => $image2->uuid, 'status' => 'new'],       // 新增
        ];

        // Mock Repository 行為（只處理 new 的圖片）
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image2->uuid)
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with($image2)
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->once();

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert
        $this->assertEquals(2, $item->images()->count()); // image1 和 image2
        $this->assertTrue($item->images->contains('uuid', $image1->uuid));
        $this->assertTrue($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：同步圖片 - 同時新增和移除
     */
    #[Test]
    public function it_should_handle_both_add_and_remove_in_same_sync(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image1 = ItemImage::factory()->create(['usage_count' => 1]);
        $image2 = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);

        // 先附加 image1
        $item->images()->attach($image1->uuid, ['sort_order' => 1]);

        $images = [
            ['uuid' => $image1->uuid, 'status' => 'removed'], // 移除
            ['uuid' => $image2->uuid, 'status' => 'new'],      // 新增
        ];

        // Mock Repository 行為
        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image1->uuid)
            ->once()
            ->andReturn($image1);

        $this->mockRepository
            ->shouldReceive('findByUuid')
            ->with($image2->uuid)
            ->once()
            ->andReturn($image2);

        $this->mockRepository
            ->shouldReceive('decrementUsageCount')
            ->with($image1)
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) use ($image1) {
                return $img->uuid === $image1->uuid && $img->usage_count <= 0;
            }), ItemImage::STATUS_DRAFT)
            ->once();

        $this->mockRepository
            ->shouldReceive('incrementUsageCount')
            ->with($image2)
            ->once();

        $this->mockRepository
            ->shouldReceive('updateStatus')
            ->with(Mockery::on(function ($img) use ($image2) {
                return $img->uuid === $image2->uuid && $img->status === ItemImage::STATUS_DRAFT;
            }), ItemImage::STATUS_USED)
            ->once();

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert
        $this->assertEquals(1, $item->images()->count()); // 只剩 image2
        $this->assertFalse($item->images->contains('uuid', $image1->uuid));
        $this->assertTrue($item->images->contains('uuid', $image2->uuid));
    }

    /**
     * 測試：同步圖片 - 避免重複 attach
     */
    #[Test]
    public function it_should_not_attach_duplicate_images(): void
    {
        // Arrange
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create(['status' => ItemImage::STATUS_DRAFT]);

        // 先附加圖片
        $item->images()->attach($image->uuid, ['sort_order' => 1]);

        $images = [
            ['uuid' => $image->uuid, 'status' => 'new'], // 嘗試再次附加
        ];

        // Mock Repository 行為
        // 由於不會 attach（因為已經存在），所以不應該呼叫 incrementUsageCount
        $this->mockRepository
            ->shouldNotReceive('findByUuid');

        $this->mockRepository
            ->shouldNotReceive('incrementUsageCount');

        $this->mockRepository
            ->shouldNotReceive('updateStatus');

        // Act
        $this->service->syncItemImages($item, $images);

        // Assert
        $this->assertEquals(1, $item->images()->count()); // 仍然只有一張
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
