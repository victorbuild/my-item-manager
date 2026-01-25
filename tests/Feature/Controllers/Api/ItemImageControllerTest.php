<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\ItemImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemImageControllerTest extends TestCase
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
     * 測試：成功上傳圖片
     */
    #[Test]
    public function it_should_upload_image_successfully(): void
    {
        // Arrange
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/item-images', [
                'image' => $file,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uuid',
                    'image_path',
                    'original_extension',
                    'status',
                    'usage_count',
                    'created_at',
                    'updated_at',
                    'original_path',
                    'preview_path',
                    'thumb_path',
                    'original_url',
                    'preview_url',
                    'thumb_url',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => '圖片上傳成功',
            ]);

        // 驗證資料庫中有記錄
        $this->assertDatabaseHas('item_images', [
            'user_id' => $this->user->id,
            'status' => ItemImage::STATUS_DRAFT,
            'usage_count' => 0,
        ]);

        // 驗證檔案已上傳
        $itemImage = ItemImage::where('user_id', $this->user->id)->first();
        $this->assertNotNull($itemImage);
        $uuid = $itemImage->uuid;
        $imagePath = $itemImage->image_path;
        $extension = $itemImage->original_extension;
        $originalPath = "item-images/{$uuid}/original_{$imagePath}.{$extension}";
        $previewPath = "item-images/{$uuid}/preview_{$imagePath}.webp";
        $thumbPath = "item-images/{$uuid}/thumb_{$imagePath}.webp";
        $previewPath = "item-images/{$itemImage->uuid}/preview_{$itemImage->image_path}.webp";
        $thumbPath = "item-images/{$itemImage->uuid}/thumb_{$itemImage->image_path}.webp";
        Storage::disk('gcs')->assertExists($originalPath);
        Storage::disk('gcs')->assertExists($previewPath);
        Storage::disk('gcs')->assertExists($thumbPath);
    }

    /**
     * 測試：未認證時應返回 401
     */
    #[Test]
    public function it_should_return_401_when_unauthenticated(): void
    {
        // Arrange
        $file = UploadedFile::fake()->image('test.jpg');

        // Act
        $response = $this->postJson('/api/item-images', [
            'image' => $file,
        ]);

        // Assert
        $response->assertStatus(401);
    }

    /**
     * 測試：缺少圖片檔案時應返回 422
     */
    #[Test]
    public function it_should_return_422_when_image_is_missing(): void
    {
        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/item-images', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    /**
     * 測試：圖片檔案過大時應返回 422
     */
    #[Test]
    public function it_should_return_422_when_image_is_too_large(): void
    {
        // Arrange
        $file = UploadedFile::fake()->create('test.jpg', 10241); // 超過 10MB

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/item-images', [
                'image' => $file,
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    /**
     * 測試：非圖片檔案時應返回 422
     */
    #[Test]
    public function it_should_return_422_when_file_is_not_image(): void
    {
        // Arrange
        $file = UploadedFile::fake()->create('test.pdf', 100);

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/item-images', [
                'image' => $file,
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    /**
     * 測試：回應格式應符合 ApiResponse 規範
     */
    #[Test]
    public function it_should_return_response_in_unified_format(): void
    {
        // Arrange
        $file = UploadedFile::fake()->image('test.jpg');

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/item-images', [
                'image' => $file,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '圖片上傳成功',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('original_url', $data);
        $this->assertArrayHasKey('preview_url', $data);
        $this->assertArrayHasKey('thumb_url', $data);
    }
}
