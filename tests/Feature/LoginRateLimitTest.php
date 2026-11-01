<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 登入 IP 總量限制必須在昂貴的人機驗證之前阻擋請求。
     */
    #[Test]
    public function it_should_block_excessive_login_requests_before_recaptcha(): void
    {
        $this->mock(RecaptchaService::class)->shouldNotReceive('verify');
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson('/login', ['email' => "member{$attempt}@example.test"])->assertUnprocessable();
        }
        $response = $this->postJson('/login', [
            'email' => 'member@example.test',
            'password' => 'test-password',
            'recaptcha_token' => 'test-token',
        ]);
        $response->assertTooManyRequests()->assertHeader('Retry-After');
        $seconds = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $seconds);
        $response->assertJsonPath('message', "登入嘗試過於頻繁，請於 {$seconds} 秒後再試。");
        $this->assertGuest();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->postJson('/login', [])->assertUnprocessable();
    }

    /**
     * 同一信箱與 IP 的五次失敗仍會限制，但不妨礙共用網路的其他帳號。
     */
    #[Test]
    public function it_should_preserve_account_ip_lockout_without_blocking_other_accounts(): void
    {
        $this->mock(RecaptchaService::class)->shouldReceive('verify')->andReturnTrue();
        User::factory()->create(['email' => 'first@example.test', 'password' => 'correct-password']);
        User::factory()->create(['email' => 'second@example.test', 'password' => 'correct-password']);
        $payload = ['email' => 'first@example.test', 'password' => 'wrong-password', 'recaptcha_token' => 'test-token'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', $payload)->assertUnauthorized();
        }
        $this->postJson('/login', $payload)->assertTooManyRequests()->assertHeader('Retry-After');
        $this->postJson('/login', [
            'email' => 'second@example.test',
            'password' => 'correct-password',
            'recaptcha_token' => 'test-token',
        ])->assertOk();
        $this->assertAuthenticated();
        $this->postJson('/logout')->assertOk();
        $this->travel(61)->seconds();
        $payload['password'] = 'correct-password';
        $this->postJson('/login', $payload)->assertOk();
    }

    /**
     * 不同分鐘的登入流量仍須遵守每小時限制，且不消耗註冊額度。
     */
    #[Test]
    public function it_should_keep_hourly_login_limits_separate_from_registration(): void
    {
        config(['auth_rate_limits.login.per_minute' => 2, 'auth_rate_limits.login.per_hour' => 3]);
        $this->postJson('/login', [])->assertUnprocessable();
        $this->postJson('/login', [])->assertUnprocessable();
        $this->postJson('/login', [])->assertTooManyRequests();
        $this->postJson('/register', [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/login', [])->assertUnprocessable();
        $response = $this->postJson('/login', []);
        $response->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertGreaterThan(60, (int) $response->headers->get('Retry-After'));
        $this->travel(3601)->seconds();
        $this->postJson('/login', [])->assertUnprocessable();
    }
}
