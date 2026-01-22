<?php

namespace Tests\Feature;

use App\Events\UserLoggedIn;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 認證控制器功能測試
 *
 * 測試登入、註冊、登出功能，以及登入紀錄的 Observer Pattern 實作
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 測試：登入成功時應該觸發 UserLoggedIn 事件
     *
     * @test
     */
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
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson(['message' => '登入成功']);

        Event::assertDispatched(UserLoggedIn::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    /**
     * 測試：登入成功時應該建立登入紀錄
     *
     * @test
     */
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
        ], [
            'User-Agent' => 'Test Browser',
        ]);

        // Assert
        $response->assertStatus(200);

        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
        ]);

        $loginLog = LoginLog::where('user_id', $user->id)->first();
        $this->assertNotNull($loginLog);
        $this->assertNotNull($loginLog->logged_in_at);
    }

    /**
     * 測試：登入成功時應該更新使用者的最後登入時間
     *
     * @test
     */
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
        ]);

        // Assert
        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->last_login_at);
    }

    /**
     * 測試：登入失敗時不應該建立登入紀錄
     *
     * @test
     */
    public function it_should_not_create_login_log_on_failed_login(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $initialCount = LoginLog::count();

        // Act
        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson(['message' => '帳號或密碼錯誤']);

        $this->assertEquals($initialCount, LoginLog::count());
    }

    /**
     * 測試：多次登入應該建立多筆登入紀錄
     *
     * @test
     */
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
        ]);

        // 登出
        $this->postJson('/logout');

        // Act - 第二次登入
        $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Assert
        $loginLogs = LoginLog::where('user_id', $user->id)->get();
        $this->assertCount(2, $loginLogs);
    }
}
