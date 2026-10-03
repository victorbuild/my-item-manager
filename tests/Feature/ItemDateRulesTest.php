<?php

namespace Tests\Feature;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemDateRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-01-31 12:00:00', 'Asia/Taipei'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 驗證月底的一個月上限，新增和編輯使用相同規則。
     */
    #[Test]
    public function it_should_allow_one_month_ahead_without_month_overflow(): void
    {
        foreach ([StoreItemRequest::class, UpdateItemRequest::class] as $class) {
            foreach (['purchased_at', 'received_at', 'used_at', 'discarded_at'] as $field) {
                $this->assertTrue($this->validateDates($class, [$field => '2026-02-28'])->passes());
                $this->assertTrue($this->validateDates($class, [$field => '2025-12-01'])->passes());
                $this->assertTrue($this->validateDates($class, [$field => '2026-03-01'])->fails());
            }
        }
    }

    /**
     * 日期相同可接受，中間日期留空仍不得違反先後順序。
     */
    #[Test]
    public function it_should_enforce_order_even_when_intermediate_dates_are_empty(): void
    {
        $fields = ['purchased_at', 'received_at', 'used_at', 'discarded_at'];
        foreach ([StoreItemRequest::class, UpdateItemRequest::class] as $class) {
            $this->assertTrue($this->validateDates($class, array_fill_keys($fields, '2026-02-10'))->passes());
            foreach ($fields as $index => $field) {
                foreach (array_slice($fields, $index + 1) as $later) {
                    $this->assertTrue($this->validateDates($class, [
                        $field => '2026-02-10', $later => '2026-02-09',
                    ])->fails());
                }
            }
        }
    }

    /**
     * 單欄位更新必須比較既有日期，明確清空則移除該限制。
     */
    #[Test]
    public function it_should_compare_existing_dates_and_allow_explicit_clearing(): void
    {
        $item = new Item(['received_at' => '2026-02-10', 'discarded_at' => '2026-02-20']);
        $this->assertTrue($this->validateDates(UpdateItemRequest::class, [
            'purchased_at' => '2026-02-11',
        ], $item)->fails());
        $this->assertTrue($this->validateDates(UpdateItemRequest::class, [
            'used_at' => '2026-02-21',
        ], $item)->fails());
        $this->assertTrue($this->validateDates(UpdateItemRequest::class, [
            'purchased_at' => '2026-02-11', 'received_at' => null,
        ], $item)->passes());
    }

    /**
     * 有效期限接受四位數年份的最大日期。
     */
    #[Test]
    public function it_should_accept_expiration_through_year_9999(): void
    {
        foreach ([StoreItemRequest::class, UpdateItemRequest::class] as $class) {
            $this->assertTrue($this->validateDates($class, ['expiration_date' => '9999-12-31'])->passes());
            $this->assertTrue($this->validateDates($class, ['expiration_date' => '10000-01-01'])->fails());
        }
    }

    /**
     * 其他欄位格式錯誤時應回傳驗證錯誤。
     */
    #[Test]
    public function it_should_reject_invalid_related_dates_without_throwing(): void
    {
        $request = StoreItemRequest::create('/', 'POST', [
            'purchased_at' => '2026-02-10',
            'received_at' => ['invalid'],
        ]);
        $rules = array_intersect_key($request->rules(), array_flip(['purchased_at', 'received_at']));
        $this->assertTrue(Validator::make($request->all(), $rules)->fails());
    }

    /**
     * 使用實際請求規則驗證日期，避免連線資料庫。
     *
     * @param class-string<\Illuminate\Foundation\Http\FormRequest> $class
     * @param array<string, string|null> $dates
     */
    private function validateDates(string $class, array $dates, ?Item $item = null): ValidatorContract
    {
        $request = $class::create('/', 'POST', $dates);
        $route = new Route('POST', '/', fn () => null);
        $route->bind($request);
        $route->setParameter('item', $item);
        $request->setRouteResolver(fn () => $route);
        $rules = array_intersect_key($request->rules(), array_flip([
            'purchased_at', 'received_at', 'used_at', 'discarded_at', 'expiration_date',
        ]));

        return Validator::make($dates, $rules);
    }
}
