<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('gcs');
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * 測試：成功取得媒體庫圖片列表
     */
    #[Test]
    public function it_should_return_media_library_images_successfully(): void
    {
        // Arrange
        $image1 = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);
        $image2 = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/media');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'image_path',
                        'original_extension',
                        'status',
                        'usage_count',
                        'created_at',
                        'updated_at',
                        'thumb_url',
                        'preview_url',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
                'quota',
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertArrayHasKey('thumb_url', $data[0]);
        $this->assertArrayHasKey('preview_url', $data[0]);
    }

    /**
     * 測試：成功取得未使用的圖片列表
     */
    #[Test]
    public function it_should_return_unused_images_successfully(): void
    {
        // Arrange
        $image1 = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
            'usage_count' => 0,
        ]);
        $image2 = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
            'usage_count' => 0,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/media/unused');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'image_path',
                        'original_extension',
                        'status',
                        'created_at',
                        'thumb_url',
                        'preview_url',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertArrayHasKey('thumb_url', $data[0]);
        $this->assertArrayHasKey('preview_url', $data[0]);
    }

    /**
     * 測試：成功取得圖片詳細資訊
     */
    #[Test]
    public function it_should_return_image_details_successfully(): void
    {
        // Arrange
        $image = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $item->images()->attach($image->uuid, ['sort_order' => 1]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/media/{$image->uuid}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'uuid',
                'image_path',
                'original_extension',
                'status',
                'usage_count',
                'created_at',
                'updated_at',
                'thumb_url',
                'preview_url',
                'original_url',
                'items' => [
                    '*' => [
                        'id',
                        'uuid',
                        'short_id',
                        'name',
                    ],
                ],
            ]);

        $responseData = $response->json();
        $this->assertEquals($image->uuid, $responseData['uuid']);
        $this->assertArrayHasKey('thumb_url', $responseData);
        $this->assertArrayHasKey('preview_url', $responseData);
        $this->assertArrayHasKey('original_url', $responseData);
        $this->assertCount(1, $responseData['items']);
    }

    /**
     * 測試：未認證時應返回 401
     */
    #[Test]
    public function it_should_return_401_when_unauthenticated(): void
    {
        // Act
        $response = $this->getJson('/api/media');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * 測試：無法查看其他用戶的圖片（Policy 檢查，返回 403）
     */
    #[Test]
    public function it_should_return_403_when_viewing_other_users_image(): void
    {
        // Arrange
        $otherUser = User::factory()->create();
        $image = ItemImage::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/media/{$image->uuid}");

        // Assert
        // Policy 檢查權限，如果沒有權限會拋出 AuthorizationException，返回 403
        $response->assertStatus(403);
    }

    /**
     * 測試：使用 ImageUrlHelper 產生 URL
     */
    #[Test]
    public function it_should_use_image_url_helper_to_generate_urls(): void
    {
        // Arrange
        $image = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/media/{$image->uuid}");

        // Assert
        $response->assertStatus(200);
        $data = $response->json();

        // 驗證 URL 格式正確（應該包含 GCS 的簽署 URL）
        $this->assertStringContainsString('http', $data['thumb_url']);
        $this->assertStringContainsString('http', $data['preview_url']);
        $this->assertStringContainsString('http', $data['original_url']);
    }

    /**
     * 測試：可以過濾有關聯的圖片
     */
    #[Test]
    public function it_should_filter_images_with_items_when_has_items_is_true(): void
    {
        // Arrange
        $imageWithItem = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);
        $imageWithoutItem = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $item->images()->attach($imageWithItem->uuid, ['sort_order' => 1]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/media?has_items=true');

        // Assert
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($imageWithItem->uuid, $data[0]['uuid']);
    }

    /**
     * 測試：可以過濾沒有關聯的圖片
     */
    #[Test]
    public function it_should_filter_images_without_items_when_has_items_is_false(): void
    {
        // Arrange
        $imageWithItem = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);
        $imageWithoutItem = ItemImage::factory()->create([
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
        ]);
        $item = Item::factory()->create(['user_id' => $this->user->id]);
        $item->images()->attach($imageWithItem->uuid, ['sort_order' => 1]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/media?has_items=false');

        // Assert
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($imageWithoutItem->uuid, $data[0]['uuid']);
    }
}
