<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreItemImageRequest;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StoreItemImageRequest 業務邏輯測試
 *
 * 只測試業務邏輯（自訂錯誤訊息、動態配置），不測試框架內建驗證規則
 */
class StoreItemImageRequestTest extends TestCase
{
    /**
     * 取得錯誤訊息
     */
    private function getMessages(): array
    {
        return (new StoreItemImageRequest())->messages();
    }

    /**
     * 測試：自訂錯誤訊息格式正確
     */
    #[Test]
    public function it_should_have_correct_custom_error_messages(): void
    {
        $messages = $this->getMessages();

        // 驗證自訂錯誤訊息內容（業務邏輯）
        $this->assertArrayHasKey('image.required', $messages);
        $this->assertArrayHasKey('image.image', $messages);
        $this->assertArrayHasKey('image.max', $messages);

        $this->assertStringContainsString('請選擇要上傳的圖片', $messages['image.required']);
        $this->assertStringContainsString('上傳的檔案必須是圖片格式', $messages['image.image']);
        $this->assertStringContainsString('圖片大小不能超過', $messages['image.max']);
    }

    /**
     * 測試：錯誤訊息中的大小限制是動態的（從 config 讀取）
     */
    #[Test]
    public function it_should_include_dynamic_size_limit_in_error_message(): void
    {
        // 設定測試用的配置值（隔離測試，不依賴實際配置檔案）
        Config::set('images.max_size', 5120); // 5MB

        $messages = $this->getMessages();

        // 驗證錯誤訊息包含動態大小限制（業務邏輯：從 config 讀取）
        $maxSizeMB = round(5120 / 1024, 1); // 5.0
        $this->assertStringContainsString((string) $maxSizeMB, $messages['image.max']);
    }

    /**
     * 測試：authorize 方法返回 true
     */
    #[Test]
    public function it_should_authorize_request(): void
    {
        $request = new StoreItemImageRequest();

        $this->assertTrue($request->authorize());
    }
}
