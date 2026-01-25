<?php

namespace Tests\Feature\Services;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use App\Repositories\ItemImageRepository;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MediaService(new ItemImageRepository());
        $this->user = User::factory()->create();
    }

    public function test_paginate_for_user_should_return_paginated_images_when_no_filters(): void
    {
        // Arrange
        ItemImage::factory()->count(5)->create(['user_id' => $this->user->id]);

        // Act
        $result = $this->service->paginateForUser($this->user->id);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(5, $result->total());
    }

    public function test_paginate_for_user_should_filter_by_status_when_status_provided(): void
    {
        // Arrange
        ItemImage::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);
        ItemImage::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'used',
        ]);

        // Act
        $result = $this->service->paginateForUser($this->user->id, 'draft');

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(3, $result->total());
        $result->each(function ($image) {
            $this->assertEquals('draft', $image->status);
        });
    }

    public function test_paginate_for_user_should_filter_by_has_items_true_when_provided(): void
    {
        // Arrange
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $imageWithItem = ItemImage::factory()->create(['user_id' => $this->user->id]);
        $imageWithoutItem = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $item->images()->attach($imageWithItem->uuid);

        // Act
        $result = $this->service->paginateForUser($this->user->id, null, true);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($imageWithItem->uuid, $result->first()->uuid);
    }

    public function test_paginate_for_user_should_filter_by_has_items_false_when_provided(): void
    {
        // Arrange
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $imageWithItem = ItemImage::factory()->create(['user_id' => $this->user->id]);
        $imageWithoutItem = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $item->images()->attach($imageWithItem->uuid);

        // Act
        $result = $this->service->paginateForUser($this->user->id, null, false);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($imageWithoutItem->uuid, $result->first()->uuid);
    }

    public function test_paginate_for_user_should_use_custom_per_page_when_provided(): void
    {
        // Arrange
        ItemImage::factory()->count(10)->create(['user_id' => $this->user->id]);

        // Act
        $result = $this->service->paginateForUser($this->user->id, null, null, 5);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(5, $result->perPage());
        $this->assertEquals(10, $result->total());
    }

    public function test_paginate_unused_for_user_should_return_paginated_unused_images(): void
    {
        // Arrange
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $imageWithItem = ItemImage::factory()->create(['user_id' => $this->user->id]);
        $imageWithoutItem = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $item->images()->attach($imageWithItem->uuid);

        // Act
        $result = $this->service->paginateUnusedForUser($this->user->id);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(1, $result->total());
        $this->assertEquals($imageWithoutItem->uuid, $result->first()->uuid);
    }

    public function test_get_quota_info_should_return_quota_info_with_unlimited(): void
    {
        // Arrange
        ItemImage::factory()->count(100)->create(['user_id' => $this->user->id]);

        // Act
        $result = $this->service->getQuotaInfo($this->user->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(100, $result['used']);
        $this->assertNull($result['limit']);
        $this->assertTrue($result['is_unlimited']);
        $this->assertEquals('2026年新年限時開放，不限制多少數量圖片', $result['message']);
        $this->assertEquals(0.0, $result['percentage']);
    }

    public function test_find_by_uuid_for_user_should_return_image_when_exists(): void
    {
        // Arrange
        $image = ItemImage::factory()->create(['user_id' => $this->user->id]);

        // Act
        $result = $this->service->findByUuidForUser($image->uuid);

        // Assert
        $this->assertInstanceOf(ItemImage::class, $result);
        $this->assertEquals($image->uuid, $result->uuid);
        $this->assertTrue($result->relationLoaded('items'));
    }

    public function test_find_by_uuid_for_user_should_throw_exception_when_not_found(): void
    {
        // Assert
        $this->expectException(ModelNotFoundException::class);

        // Act
        $this->service->findByUuidForUser('non-existent-uuid');
    }

    public function test_find_by_uuid_for_user_should_return_image_even_when_belongs_to_different_user(): void
    {
        // Arrange
        $otherUser = User::factory()->create();
        $image = ItemImage::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $result = $this->service->findByUuidForUser($image->uuid);

        // Assert
        // Service 層不過濾 user_id，權限檢查由 Controller 層的 Policy 處理
        $this->assertInstanceOf(ItemImage::class, $result);
        $this->assertEquals($image->uuid, $result->uuid);
    }
}
