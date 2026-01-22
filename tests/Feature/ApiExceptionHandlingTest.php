<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * API 異常處理整合測試
 *
 * 測試全局異常處理器是否正確處理各種異常情況
 * 確保所有 API 錯誤回應使用統一格式
 */
class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * 測試：404 錯誤 - 統一格式
     *
     * @test
     */
    public function it_should_return_unified_format_for_404_error(): void
    {
        // Arrange
        $this->actingAs($this->user);

        // Act - 請求不存在的資源
        $response = $this->getJson('/api/items/non-existent-id');

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'message' => '找不到指定的資源',
            ])
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    /**
     * 測試：401 錯誤 - 統一格式（未認證）
     *
     * @test
     */
    public function it_should_return_unified_format_for_401_error(): void
    {
        // Arrange - 不登入

        // Act - 嘗試存取需要認證的資源
        $response = $this->getJson('/api/items');

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJson([
                'success' => false,
                'message' => '未授權，請先登入',
            ])
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    /**
     * 測試：422 錯誤 - 統一格式（驗證失敗）
     *
     * @test
     */
    public function it_should_return_unified_format_for_422_error(): void
    {
        // Arrange
        $this->actingAs($this->user);

        // Act - 發送無效的資料
        $response = $this->postJson('/api/items', [
            'name' => '', // 必填欄位為空
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson([
                'success' => false,
                'message' => '驗證失敗',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    /**
     * 測試：沒有 Accept header 時，預設返回 JSON
     *
     * @test
     */
    public function it_should_return_json_when_no_accept_header(): void
    {
        // Arrange
        $this->actingAs($this->user);

        // Act - 不設定 Accept header，請求不存在的資源
        $response = $this->get('/api/items/non-existent-id', [
            'Accept' => null, // 不設定 Accept header
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'success' => false,
                'message' => '找不到指定的資源',
            ]);
    }

    /**
     * 測試：Accept header 是 JSON 時，返回 JSON
     *
     * @test
     */
    public function it_should_return_json_when_accept_header_is_json(): void
    {
        // Arrange
        $this->actingAs($this->user);

        // Act - 設定 Accept header 為 JSON
        $response = $this->get('/api/items/non-existent-id', [
            'Accept' => 'application/json',
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'success' => false,
                'message' => '找不到指定的資源',
            ]);
    }

    /**
     * 測試：Accept header 是 XML 時，目前返回 JSON（預留未來擴展）
     *
     * @test
     */
    public function it_should_return_json_when_accept_header_is_xml(): void
    {
        // Arrange
        $this->actingAs($this->user);

        // Act - 設定 Accept header 為 XML
        $response = $this->get('/api/items/non-existent-id', [
            'Accept' => 'application/xml',
        ]);

        // Assert
        // 目前只支援 JSON，所以即使要求 XML 也返回 JSON
        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson([
                'success' => false,
                'message' => '找不到指定的資源',
            ]);
    }
}
