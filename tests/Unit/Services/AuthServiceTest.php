<?php

namespace Tests\Unit\Services;

use App\Events\UserLoggedIn;
use App\Events\UserLoginFailed;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AuthService 單元測試
 *
 * 透過 mock Auth、RateLimiter、UserRepository 等，隔離 Service 邏輯，驗證：
 * - attemptLogin 成功：clear、UserLoggedIn、回傳 User
 * - attemptLogin 失敗：increment、UserLoginFailed、AuthenticationException
 * - 限流：ThrottleRequestsException
 * - register：UserRepository->create、Auth::login、回傳 User
 */
class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    /**
     * @var \Mockery\MockInterface&\App\Repositories\Contracts\UserRepositoryInterface
     */
    private $mockUserRepository;

    private array $credentials = [
        'email' => 'test@example.com',
        'password' => 'password',
    ];

    private string $ip = '192.168.1.1';

    private ?string $userAgent = 'TestAgent/1.0';

    private array $registerValidated = [
        'name' => 'Test User',
        'email' => 'register@example.com',
        'password' => 'password123',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUserRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->authService = new AuthService($this->mockUserRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 登入成功：應 clear Rate limit、觸發 UserLoggedIn、回傳 User
     */
    #[Test]
    public function it_should_clear_rate_limit_and_dispatch_user_logged_in_when_login_succeeds(): void
    {
        Event::fake();

        $user = User::factory()->make(['email' => $this->credentials['email']]);
        $user->id = 1;

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->with(Mockery::type('string'), 5)
            ->andReturn(false);

        Auth::shouldReceive('attempt')
            ->once()
            ->with($this->credentials)
            ->andReturn(true);

        RateLimiter::shouldReceive('clear')
            ->once()
            ->with(Mockery::on(fn (string $k) => str_ends_with($k, '|' . $this->ip)))
            ->andReturn(null);

        Auth::shouldReceive('user')
            ->andReturn($user);

        $result = $this->authService->attemptLogin($this->credentials, $this->ip, $this->userAgent);

        $this->assertSame($user, $result);

        Event::assertDispatched(UserLoggedIn::class, function (UserLoggedIn $e) use ($user) {
            return $e->user === $user
                && $e->ipAddress === $this->ip
                && $e->userAgent === $this->userAgent;
        });
    }

    /**
     * 登入失敗：應 increment、觸發 UserLoginFailed、拋出 AuthenticationException
     */
    #[Test]
    public function it_should_increment_and_dispatch_user_login_failed_when_login_fails(): void
    {
        Event::fake();

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->with(Mockery::type('string'), 5)
            ->andReturn(false);

        Auth::shouldReceive('attempt')
            ->once()
            ->with($this->credentials)
            ->andReturn(false);

        RateLimiter::shouldReceive('increment')
            ->once()
            ->with(Mockery::on(fn (string $k) => str_contains($k, $this->credentials['email'])))
            ->andReturn(1);

        RateLimiter::shouldReceive('hit')->never();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('帳號或密碼錯誤');

        $this->authService->attemptLogin($this->credentials, $this->ip, $this->userAgent);

        Event::assertDispatched(UserLoginFailed::class, function (UserLoginFailed $e) {
            return $e->email === $this->credentials['email']
                && $e->ipAddress === $this->ip
                && $e->userAgent === $this->userAgent;
        });
    }

    /**
     * 登入失敗且達最大次數：應呼叫 hit 再觸發 UserLoginFailed
     */
    #[Test]
    public function it_should_hit_rate_limiter_when_failure_reaches_max_attempts(): void
    {
        Event::fake();

        RateLimiter::shouldReceive('tooManyAttempts')->once()->andReturn(false);
        Auth::shouldReceive('attempt')->once()->andReturn(false);
        RateLimiter::shouldReceive('increment')->once()->andReturn(5);
        RateLimiter::shouldReceive('hit')
            ->once()
            ->with(Mockery::type('string'), 60)
            ->andReturn(null);

        $this->expectException(AuthenticationException::class);

        $this->authService->attemptLogin($this->credentials, $this->ip, null);

        Event::assertDispatched(UserLoginFailed::class);
    }

    /**
     * 已達 Rate limit：應拋出 ThrottleRequestsException，且不呼叫 attempt
     */
    #[Test]
    public function it_should_throw_throttle_exception_when_too_many_attempts(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->with(Mockery::type('string'), 5)
            ->andReturn(true);

        RateLimiter::shouldReceive('availableIn')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn(42);

        Auth::shouldReceive('attempt')->never();

        $this->expectException(ThrottleRequestsException::class);
        $this->expectExceptionMessage('嘗試次數過多，請 42 秒後再試');

        $this->authService->attemptLogin($this->credentials, $this->ip, $this->userAgent);
    }

    /**
     * 註冊成功：應透過 UserRepository 建立使用者、Auth::login 登入、回傳 User
     */
    #[Test]
    public function it_should_create_user_via_repository_and_login_when_register_succeeds(): void
    {
        $user = User::factory()->make([
            'name' => $this->registerValidated['name'],
            'email' => $this->registerValidated['email'],
        ]);
        $user->id = 1;

        $this->mockUserRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['name'] === $this->registerValidated['name']
                    && $data['email'] === $this->registerValidated['email']
                    && $data['password'] === $this->registerValidated['password'];
            }))
            ->andReturn($user);

        Auth::shouldReceive('login')
            ->once()
            ->with($user);

        $result = $this->authService->register($this->registerValidated);

        $this->assertSame($user, $result);
    }
}
