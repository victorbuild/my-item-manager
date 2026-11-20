<?php

namespace Tests\Feature;

use App\Events\UserLoggedIn;
use App\Events\UserLoginFailed;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * 認證控制器功能測試
 *
 * 測試登入、註冊、登出功能，以及登入紀錄的 Observer Pattern 實作
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(RecaptchaService::class)
            ->shouldReceive('verify')
            ->andReturn(true);
    }

    /**
     * 不可信來源缺少 CSRF token 時，即使帳密正確也不得登入。
     */
    #[Test]
    public function it_should_reject_login_when_cross_site_request_has_no_csrf_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $environment = $this->app->environment();

        // Laravel 在 testing 環境會略過 CSRF，暫時切換以執行真實的 middleware。
        $this->app->instance('env', 'production');

        try {
            $this->postJson('/login', [
                'email' => $user->email,
                'password' => 'password',
                'recaptcha_token' => 'test-token',
            ], [
                'Origin' => 'https://untrusted.example',
                'Referer' => 'https://untrusted.example/',
                'Sec-Fetch-Site' => 'cross-site',
            ])->assertStatus(419);

            $this->assertGuest('web');
            $this->assertDatabaseCount('login_logs', 0);
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    /**
     * 合法來源取得 CSRF cookie 後，應能攜帶有效 token 正常登入。
     */
    #[Test]
    public function it_should_authenticate_user_when_same_origin_request_has_valid_csrf_token(): void
    {
        config(['session.driver' => 'database', 'session.secure' => false]);
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $environment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $csrfResponse = $this->get('/sanctum/csrf-cookie')->assertNoContent();
            $sessionCookie = $csrfResponse->getCookie(config('session.cookie'));
            $csrfCookie = $csrfResponse->getCookie('XSRF-TOKEN', false);
            $this->assertNotNull($sessionCookie);
            $this->assertNotNull($csrfCookie);

            // 重新載入 session，確認登入使用的是前一次請求建立的 cookie。
            $this->app->make('session')->forgetDrivers();
            $this->app->forgetInstance('session.store');

            $this->withCredentials()
                ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
                ->postJson('/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'recaptcha_token' => 'test-token',
                ], [
                    'Origin' => 'http://localhost',
                    'Referer' => 'http://localhost/login',
                    'Sec-Fetch-Site' => 'same-origin',
                    'X-XSRF-TOKEN' => $csrfCookie->getValue(),
                ])->assertOk();

            $this->assertAuthenticatedAs($user, 'web');
        } finally {
            $this->app->instance('env', $environment);
        }
    }

    /**
     * PHP 與 JSON 格式的 session 在登入後，皆應能透過 cookie 保留登入狀態。
     */
    #[Test]
    #[TestWith(['php'])]
    #[TestWith(['json'])]
    public function it_should_keep_session_authenticated_when_calling_user_api_after_login(string $serialization): void
    {
        config([
            'session.driver' => 'database',
            'session.secure' => false,
            'session.serialization' => $serialization,
        ]);
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $login = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
            'recaptcha_token' => 'test-token',
        ]);
        $login->assertOk();
        $cookie = $login->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie);

        // 清除同一測試程序內的 guard 與 session，模擬新的 HTTP 請求。
        $this->app->make('auth')->forgetGuards();
        $this->app->make('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');

        $this->withCredentials()
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->getJson('/api/user', ['Referer' => 'http://localhost/'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    /**
     * 測試：登入成功時應該觸發 UserLoggedIn 事件
     */
    #[Test]
    public function it_should_dispatch_user_logged_in_event_on_successful_login(): void
    {
        // Arrange
        Event::fake();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Act
        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'recaptcha_token' => 'test-token',
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '登入成功',
            ]);

        Event::assertDispatched(UserLoggedIn::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    /**
     * 測試：登入成功時應該建立登入紀錄
     */
    #[Test]
    public function it_should_create_login_log_on_successful_login(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Act
        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'recaptcha_token' => 'test-token',
        ], [
            'User-Agent' => 'Test Browser',
        ]);

        // Assert
        $response->assertStatus(200);

        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'status' => 'success',
        ]);

        $loginLog = LoginLog::where('user_id', $user->id)->first();
        $this->assertNotNull($loginLog);
        $this->assertNotNull($loginLog->logged_in_at);
    }

    /**
     * 測試：登入成功時應該更新使用者的最後登入時間
     */
    #[Test]
    public function it_should_update_user_last_login_at_on_successful_login(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->assertNull($user->last_login_at);

        // Act
        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'recaptcha_token' => 'test-token',
        ]);

        // Assert
        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->last_login_at);
    }

    /**
     * 測試：登入失敗時應該建立失敗登入紀錄
     */
    #[Test]
    public function it_should_create_failed_login_log_on_failed_login(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Act
        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'recaptcha_token' => 'test-token',
        ], [
            'User-Agent' => 'Test Browser',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => '帳號或密碼錯誤',
            ]);

        $this->assertDatabaseHas('login_logs', [
            'user_id' => null, // 失敗時沒有 user_id
            'email' => 'test@example.com',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'status' => 'failed',
        ]);

        $failedLog = LoginLog::where('status', 'failed')->first();
        $this->assertNotNull($failedLog);
        $this->assertNull($failedLog->user_id);
        $this->assertEquals('test@example.com', $failedLog->email);
    }

    /**
     * 測試：登入失敗時應該觸發 UserLoginFailed 事件
     */
    #[Test]
    public function it_should_dispatch_user_login_failed_event_on_failed_login(): void
    {
        // Arrange
        Event::fake();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Act
        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'recaptcha_token' => 'test-token',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => '帳號或密碼錯誤',
            ]);

        Event::assertDispatched(UserLoginFailed::class, function ($event) {
            return $event->email === 'test@example.com';
        });
    }

    /**
     * 測試：Email 不存在時也應該記錄失敗登入
     */
    #[Test]
    public function it_should_create_failed_login_log_when_email_not_exists(): void
    {
        // Arrange - 不建立任何使用者

        // Act
        $response = $this->postJson('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
            'recaptcha_token' => 'test-token',
        ], [
            'User-Agent' => 'Test Browser',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => '帳號或密碼錯誤',
            ]);

        // 應該記錄失敗登入，即使 email 不存在
        $this->assertDatabaseHas('login_logs', [
            'user_id' => null,
            'email' => 'nonexistent@example.com',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'status' => 'failed',
        ]);

        $failedLog = LoginLog::where('email', 'nonexistent@example.com')->first();
        $this->assertNotNull($failedLog);
        $this->assertEquals('failed', $failedLog->status);
    }

    /**
     * 測試：多次登入應該建立多筆登入紀錄
     */
    #[Test]
    public function it_should_create_multiple_login_logs_for_multiple_logins(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Act - 第一次登入
        $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'recaptcha_token' => 'test-token',
        ]);

        // 登出
        $this->postJson('/logout');

        // Act - 第二次登入
        $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'recaptcha_token' => 'test-token',
        ]);

        // Assert
        $loginLogs = LoginLog::where('user_id', $user->id)->get();
        $this->assertCount(2, $loginLogs);
    }
}
