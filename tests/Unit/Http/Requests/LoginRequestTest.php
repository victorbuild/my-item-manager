<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LoginRequest 驗證規則測試
 *
 * 測試登入表單的驗證規則是否符合預期
 */
class LoginRequestTest extends TestCase
{
    /**
     * 取得驗證規則
     */
    private function getRules(): array
    {
        return (new LoginRequest())->rules();
    }

    /**
     * 測試：有效的資料應該通過驗證
     */
    #[Test]
    public function it_should_pass_validation_with_valid_data(): void
    {
        $validator = Validator::make([
            'email' => 'test@example.com',
            'password' => 'password123',
        ], $this->getRules());

        $this->assertTrue($validator->passes());
    }

    /**
     * 測試：email 為必填欄位
     *
     * @param mixed $emailValue 測試的 email 值
     */
    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function it_should_fail_validation_when_email_is_invalid($emailValue): void
    {
        $validator = Validator::make([
            'email' => $emailValue,
            'password' => 'password123',
        ], $this->getRules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    /**
     * 測試：password 為必填欄位
     *
     * @param mixed $passwordValue 測試的 password 值
     */
    #[Test]
    #[DataProvider('invalidPasswordProvider')]
    public function it_should_fail_validation_when_password_is_invalid($passwordValue): void
    {
        $validator = Validator::make([
            'email' => 'test@example.com',
            'password' => $passwordValue,
        ], $this->getRules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    /**
     * 測試：authorize 方法應該返回 true
     *
     * 登入請求允許任何人嘗試（因為登入本身是給未登入的使用者使用的）
     */
    #[Test]
    public function it_should_authorize_request(): void
    {
        $request = new LoginRequest();

        $this->assertTrue($request->authorize());
    }

    /**
     * Email 無效值 Data Provider
     *
     * @return array<string, array<mixed>>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'email 缺失' => [null],
            'email 為空字串' => [''],
            'email 格式無效' => ['invalid-email'],
            'email 缺少 @ 符號' => ['testexample.com'],
            'email 缺少域名' => ['test@'],
            'email 缺少使用者名稱' => ['@example.com'],
        ];
    }

    /**
     * Password 無效值 Data Provider
     *
     * @return array<string, array<mixed>>
     */
    public static function invalidPasswordProvider(): array
    {
        return [
            'password 缺失' => [null],
            'password 為空字串' => [''],
        ];
    }
}
