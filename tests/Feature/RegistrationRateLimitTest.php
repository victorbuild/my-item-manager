<?php

namespace Tests\Feature;

use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 超出分鐘額度時，須在驗證人機與建立帳號之前阻擋。
     */
    #[Test]
    public function it_should_block_registration_before_verification_when_minute_limit_is_reached(): void
    {
        $this->mock(RecaptchaService::class)->shouldNotReceive('verify');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/register', ['email' => 'member@example.test'])->assertUnprocessable();
        }

        $response = $this->postJson('/register', $this->validRegistration());
        $response->assertTooManyRequests()
            ->assertJsonPath('success', false)
            ->assertHeader('Retry-After');
        $seconds = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $seconds);
        $this->assertLessThanOrEqual(60, $seconds);
        $response->assertJsonPath('message', "註冊嘗試過於頻繁，請於 {$seconds} 秒後再試。");
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * 分鐘額度冷卻後仍要遵守小時額度，不能只靠等待一分鐘繞過。
     */
    #[Test]
    public function it_should_enforce_hour_limit_when_requests_are_spread_across_minutes(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $this->postJson('/register', ['email' => "member{$attempt}@example.test"])->assertUnprocessable();
            if (($attempt + 1) % 20 === 0) {
                $this->travel(61)->seconds();
            }
        }

        $response = $this->postJson('/register', []);
        $response->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertGreaterThan(60, (int) $response->headers->get('Retry-After'));
        $this->assertDatabaseCount('users', 0);
        $this->travel(3601)->seconds();
        $this->postJson('/register', [])->assertUnprocessable();
    }

    /**
     * 不同 IP 不共用註冊額度，正常註冊仍須走原驗證與登入流程。
     */
    #[Test]
    public function it_should_allow_another_ip_to_register_when_one_ip_is_throttled(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->postJson('/register', ['email' => "member{$attempt}@example.test"])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/register', [])->assertTooManyRequests();

        $this->mock(RecaptchaService::class)->shouldReceive('verify')->once()->andReturnTrue();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->postJson('/register', $this->validRegistration())
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticated();
    }

    /**
     * 分鐘冷卻恢復後可重試，且註冊限制不應套用到登入入口。
     */
    #[Test]
    public function it_should_restore_minute_budget_without_throttling_login(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/register', [])->assertUnprocessable();
        }
        $this->postJson('/register', [])->assertTooManyRequests();
        $this->postJson('/login', [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/register', [])->assertUnprocessable();
    }

    /**
     * 更換轉送標頭、信箱或瀏覽器識別不能繞過同一來源 IP 的額度。
     */
    #[Test]
    public function it_should_keep_the_same_budget_when_untrusted_client_headers_change(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/register', ['email' => "member{$attempt}@example.test"], [
                'X-Forwarded-For' => '192.0.2.' . ($attempt + 1),
                'User-Agent' => 'Browser-' . $attempt,
            ])->assertUnprocessable();
        }
        $this->postJson('/register', [], [
            'X-Forwarded-For' => '192.0.2.100',
            'User-Agent' => 'Another-Browser',
        ])->assertTooManyRequests();
    }

    /**
     * 限流門檻集中設定，不在路由或控制器內寫死。
     */
    #[Test]
    public function it_should_apply_configured_limits_when_registration_thresholds_change(): void
    {
        config(['auth_rate_limits.registration.per_minute' => 2]);
        $this->postJson('/register', [])->assertUnprocessable();
        $this->postJson('/register', [])->assertUnprocessable();
        $this->postJson('/register', [])->assertTooManyRequests();
    }

    /**
     * 共用網路的一個信箱達限制後，其他信箱仍可正常註冊。
     */
    #[Test]
    public function it_should_allow_another_email_on_shared_ip_when_one_email_is_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $email = $attempt % 2 === 0 ? 'Member@Example.Test' : 'member@example.test';
            $this->postJson('/register', ['email' => $email])->assertUnprocessable();
        }
        $this->postJson('/register', ['email' => 'member@example.test'])->assertTooManyRequests();
        $this->mock(RecaptchaService::class)->shouldReceive('verify')->once()->andReturnTrue();
        $payload = $this->validRegistration();
        $payload['email'] = 'another@example.test';
        $this->postJson('/register', $payload)->assertOk();
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * 產生可用於確認限流先於註冊驗證執行的資料。
     *
     * @return array<string, string>
     */
    private function validRegistration(): array
    {
        return [
            'name' => 'Test Member',
            'email' => 'member@example.test',
            'password' => 'test-password-123',
            'password_confirmation' => 'test-password-123',
            'recaptcha_token' => 'test-token',
        ];
    }
}
