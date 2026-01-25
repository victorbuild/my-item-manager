<?php

namespace Tests\Unit\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductPolicyTest extends TestCase
{
    private ProductPolicy $policy;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ProductPolicy();
        $this->user = User::factory()->make();
        $this->user->id = 1;
        $this->otherUser = User::factory()->make();
        $this->otherUser->id = 2;
    }

    /**
     * 測試：擁有者可以檢視產品
     */
    #[Test]
    public function it_should_allow_owner_to_view_product(): void
    {
        $product = Product::factory()->make(['user_id' => $this->user->id]);

        $result = $this->policy->view($this->user, $product);

        $this->assertTrue($result);
    }

    /**
     * 測試：非擁有者不能檢視產品
     */
    #[Test]
    public function it_should_deny_other_user_to_view_product(): void
    {
        $product = Product::factory()->make(['user_id' => $this->user->id]);

        $result = $this->policy->view($this->otherUser, $product);

        $this->assertFalse($result);
    }

    /**
     * 測試：擁有者可以更新產品
     */
    #[Test]
    public function it_should_allow_owner_to_update_product(): void
    {
        $product = Product::factory()->make(['user_id' => $this->user->id]);

        $result = $this->policy->update($this->user, $product);

        $this->assertTrue($result);
    }

    /**
     * 測試：非擁有者不能更新產品
     */
    #[Test]
    public function it_should_deny_other_user_to_update_product(): void
    {
        $product = Product::factory()->make(['user_id' => $this->user->id]);

        $result = $this->policy->update($this->otherUser, $product);

        $this->assertFalse($result);
    }

    /**
     * 測試：擁有者可以刪除產品
     */
    #[Test]
    public function it_should_allow_owner_to_delete_product(): void
    {
        $product = Product::factory()->make(['user_id' => $this->user->id]);

        $result = $this->policy->delete($this->user, $product);

        $this->assertTrue($result);
    }

    /**
     * 測試：非擁有者不能刪除產品
     */
    #[Test]
    public function it_should_deny_other_user_to_delete_product(): void
    {
        $product = Product::factory()->make(['user_id' => $this->user->id]);

        $result = $this->policy->delete($this->otherUser, $product);

        $this->assertFalse($result);
    }
}
