<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\ExpiringSoonRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ExpiringSoonRequest 驗證規則測試
 *
 * 測試 expiringSoon 端點的驗證規則是否符合預期
 */
class ExpiringSoonRequestTest extends TestCase
{
    /**
     * 取得驗證規則
     */
    private function getRules(): array
    {
        return (new ExpiringSoonRequest())->rules();
    }

    /**
     * 測試：有效的資料應該通過驗證
     */
    #[Test]
    public function it_should_pass_validation_with_valid_data(): void
    {
        $validator = Validator::make([
            'days' => 30,
            'per_page' => 20,
        ], $this->getRules());

        $this->assertTrue($validator->passes());
    }

    /**
     * 測試：days 為可選欄位，缺失時應該通過驗證
     */
    #[Test]
    public function it_should_pass_validation_when_days_is_missing(): void
    {
        $validator = Validator::make([
            'per_page' => 20,
        ], $this->getRules());

        $this->assertTrue($validator->passes());
    }

    /**
     * 測試：per_page 為可選欄位，缺失時應該通過驗證
     */
    #[Test]
    public function it_should_pass_validation_when_per_page_is_missing(): void
    {
        $validator = Validator::make([
            'days' => 30,
        ], $this->getRules());

        $this->assertTrue($validator->passes());
    }

    /**
     * 測試：days 驗證規則
     *
     * @param mixed $daysValue 測試的 days 值
     * @param bool $shouldPass 是否應該通過驗證
     */
    #[Test]
    #[DataProvider('daysValidationProvider')]
    public function it_should_validate_days_correctly($daysValue, bool $shouldPass): void
    {
        $validator = Validator::make([
            'days' => $daysValue,
            'per_page' => 20,
        ], $this->getRules());

        if ($shouldPass) {
            $this->assertTrue($validator->passes(), "days = {$daysValue} 應該通過驗證");
        } else {
            $this->assertFalse($validator->passes(), "days = {$daysValue} 應該失敗驗證");
            $this->assertArrayHasKey('days', $validator->errors()->toArray());
        }
    }

    /**
     * 測試：per_page 驗證規則
     *
     * @param mixed $perPageValue 測試的 per_page 值
     * @param bool $shouldPass 是否應該通過驗證
     */
    #[Test]
    #[DataProvider('perPageValidationProvider')]
    public function it_should_validate_per_page_correctly($perPageValue, bool $shouldPass): void
    {
        $validator = Validator::make([
            'days' => 30,
            'per_page' => $perPageValue,
        ], $this->getRules());

        if ($shouldPass) {
            $this->assertTrue($validator->passes(), "per_page = {$perPageValue} 應該通過驗證");
        } else {
            $this->assertFalse($validator->passes(), "per_page = {$perPageValue} 應該失敗驗證");
            $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
        }
    }

    /**
     * 測試：驗證規則允許 nullable，表示可以缺失（預設值由 prepareForValidation 處理）
     */
    #[Test]
    public function it_should_allow_nullable_fields(): void
    {
        // 驗證規則允許 nullable，表示可以缺失
        $rules = $this->getRules();

        $this->assertStringContainsString('nullable', $rules['days']);
        $this->assertStringContainsString('nullable', $rules['per_page']);
    }

    /**
     * 測試：prepareForValidation 應該設定預設值
     *
     * 注意：完整的預設值測試會在功能測試中驗證（實際 HTTP 請求）
     */
    #[Test]
    public function it_should_have_prepare_for_validation_method(): void
    {
        $request = new ExpiringSoonRequest();

        // 確認 prepareForValidation 方法存在
        $this->assertTrue(method_exists($request, 'prepareForValidation'));
    }

    /**
     * 測試：authorize 方法應該檢查使用者是否已登入
     */
    #[Test]
    public function it_should_authorize_request_when_user_is_authenticated(): void
    {
        Auth::shouldReceive('check')->andReturn(true);

        $request = new ExpiringSoonRequest();

        $this->assertTrue($request->authorize());
    }

    /**
     * Days 驗證規則 Data Provider
     *
     * 只測試我們定義的業務規則（min:1, max:1095），不測試框架的 integer 規則行為
     *
     * @return array<string, array{mixed, bool}>
     */
    public static function daysValidationProvider(): array
    {
        return [
            'days equals 1 (minimum boundary)' => [1, true],
            'days equals 30 (default value)' => [30, true],
            'days equals 1095 (maximum boundary)' => [1095, true],
            'days equals 0 (below minimum)' => [0, false],
            'days equals -1 (negative, below minimum)' => [-1, false],
            'days equals 1096 (exceeds maximum)' => [1096, false],
        ];
    }

    /**
     * Per Page 驗證規則 Data Provider
     *
     * 只測試我們定義的業務規則（min:1, max:100），不測試框架的 integer 規則行為
     *
     * @return array<string, array{mixed, bool}>
     */
    public static function perPageValidationProvider(): array
    {
        return [
            'per_page equals 1 (minimum boundary)' => [1, true],
            'per_page equals 20 (default value)' => [20, true],
            'per_page equals 100 (maximum boundary)' => [100, true],
            'per_page equals 0 (below minimum)' => [0, false],
            'per_page equals -1 (negative, below minimum)' => [-1, false],
            'per_page equals 101 (exceeds maximum)' => [101, false],
        ];
    }
}
