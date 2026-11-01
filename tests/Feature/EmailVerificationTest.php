<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_should_send_verification_when_registration_succeeds(): void
    {
        Notification::fake();
        $this->mock(RecaptchaService::class)->shouldReceive('verify')->andReturn(true);
        $this->postJson('/register', [
            'name' => '會員', 'email' => 'member@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'recaptcha_token' => 'test-token',
        ])->assertSuccessful()->assertJsonPath('data.email_verified_at', null);
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->postJson('/api/email/verification-notification')->assertTooManyRequests();
    }

    #[Test]
    public function it_should_block_management_but_allow_account_and_resend_when_unverified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->getJson('/api/user')->assertOk();
        $urls = ['/api/items', '/api/products', '/api/categories', '/api/media', '/api/items/statistics/overview'];
        foreach ($urls as $url) {
            $this->getJson($url)->assertForbidden()->assertJsonPath('code', 'email_verification_required');
        }
        $this->postJson('/api/items', [])->assertForbidden();
        $this->postJson('/api/email/verification-notification', ['email' => 'other@example.test'])->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertCount(1);
    }

    #[Test]
    public function it_should_verify_once_when_the_owner_opens_a_valid_link(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = $this->link($user);
        $this->actingAs($user)->get($url)->assertRedirect('/verify-email?verified=1');
        $this->get($url)->assertRedirect('/verify-email?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->getJson('/api/categories')->assertOk();
    }

    #[Test]
    public function it_should_reject_expired_tampered_and_other_account_links(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = $this->link($user);
        $this->actingAs($other)->get($url)->assertRedirect('/verify-email?invalid=1');
        $this->actingAs($user)->get($url . 'x')->assertRedirect('/verify-email?invalid=1');
        $this->travel(61)->minutes();
        $this->get($url)->assertRedirect('/verify-email?invalid=1');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function it_should_keep_verification_destination_when_a_guest_logs_in(): void
    {
        $this->mock(RecaptchaService::class)->shouldReceive('verify')->andReturn(true);
        $user = User::factory()->unverified()->create(['password' => 'password123']);
        $url = $this->link($user);
        $this->get($url)->assertRedirect('/login');
        $credentials = ['email' => $user->email, 'password' => 'password123', 'recaptcha_token' => 'test-token'];
        $response = $this->postJson('/login', $credentials);
        $response->assertOk()->assertJsonPath('data.email_verified', false);
        $redirect = $response->json('data.verification_redirect');
        $this->assertStringStartsWith('/email/verify/', $redirect);
        $this->get($redirect)->assertRedirect('/verify-email?verified=1');
    }

    #[Test]
    public function it_should_limit_resends_per_account_across_ips_and_allow_recovery(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->postJson('/api/email/verification-notification')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.55'])
            ->postJson('/api/email/verification-notification')->assertTooManyRequests()->assertHeader('Retry-After');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->travel(61)->seconds();
            $this->postJson('/api/email/verification-notification')->assertOk();
        }
        $this->travel(61)->seconds();
        $this->postJson('/api/email/verification-notification')->assertTooManyRequests();
        Notification::assertCount(6);
        $this->travel(3601)->seconds();
        $this->postJson('/api/email/verification-notification')->assertOk();
    }

    #[Test]
    public function it_should_not_send_mail_when_verified_or_unauthenticated(): void
    {
        Notification::fake();
        $this->postJson('/api/email/verification-notification')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/email/verification-notification')->assertOk();
        Notification::assertNothingSent();
    }

    #[Test]
    public function it_should_preserve_the_registered_account_when_mail_delivery_fails(): void
    {
        $this->mock(RecaptchaService::class)->shouldReceive('verify')->andReturn(true);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $this->postJson('/register', [
            'name' => '會員', 'email' => 'member@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'recaptcha_token' => 'test-token',
        ])->assertSuccessful();
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->getJson('/api/items')->assertForbidden();
    }

    #[Test]
    public function it_should_ignore_external_login_destinations(): void
    {
        $this->mock(RecaptchaService::class)->shouldReceive('verify')->andReturn(true);
        $user = User::factory()->create(['password' => 'password123']);
        $this->withSession(['url.intended' => 'https://example.invalid/email/verify/1/abc?signature=test'])
            ->postJson('/login', [
                'email' => $user->email, 'password' => 'password123', 'recaptcha_token' => 'test-token',
            ])
            ->assertOk()->assertJsonPath('data.verification_redirect', null);
    }

    #[Test]
    public function it_should_remove_access_when_an_unverified_account_logs_out(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->getJson('/api/user')->assertOk();
        $this->postJson('/logout')->assertOk();
        $this->assertGuest('web');
        // HTTP 測試共用容器；清除上一個 API 請求的 guard 快取以模擬下一個獨立請求。
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/items')->assertUnauthorized();
    }

    private function link(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
    }
}
