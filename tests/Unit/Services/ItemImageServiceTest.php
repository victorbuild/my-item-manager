<?php

namespace Tests\Unit\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Repositories\Contracts\ItemImageRepositoryInterface;
use App\Services\ItemImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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
     * @test
     */
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
     * @test
     */
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
     * @test
     */
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
     * @test
     */
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
     * @test
     */
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
     * @test
     */
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
     * @test
     */
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
     * @test
     */
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
}
