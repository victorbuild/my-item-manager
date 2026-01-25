<?php

namespace Tests\Unit\Policies;

use App\Models\ItemImage;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MediaPolicy $policy;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new MediaPolicy();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /**
     * 測試：擁有者可以檢視圖片
     */
    #[Test]
    public function it_should_allow_owner_to_view_item_image(): void
    {
        $itemImage = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $result = $this->policy->view($this->user, $itemImage);

        $this->assertTrue($result);
    }

    /**
     * 測試：非擁有者不能檢視圖片
     */
    #[Test]
    public function it_should_deny_other_user_to_view_item_image(): void
    {
        $itemImage = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $result = $this->policy->view($this->otherUser, $itemImage);

        $this->assertFalse($result);
    }

    /**
     * 測試：擁有者可以更新圖片
     */
    #[Test]
    public function it_should_allow_owner_to_update_item_image(): void
    {
        $itemImage = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $result = $this->policy->update($this->user, $itemImage);

        $this->assertTrue($result);
    }

    /**
     * 測試：非擁有者不能更新圖片
     */
    #[Test]
    public function it_should_deny_other_user_to_update_item_image(): void
    {
        $itemImage = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $result = $this->policy->update($this->otherUser, $itemImage);

        $this->assertFalse($result);
    }

    /**
     * 測試：擁有者可以刪除圖片
     */
    #[Test]
    public function it_should_allow_owner_to_delete_item_image(): void
    {
        $itemImage = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $result = $this->policy->delete($this->user, $itemImage);

        $this->assertTrue($result);
    }

    /**
     * 測試：非擁有者不能刪除圖片
     */
    #[Test]
    public function it_should_deny_other_user_to_delete_item_image(): void
    {
        $itemImage = ItemImage::factory()->create(['user_id' => $this->user->id]);

        $result = $this->policy->delete($this->otherUser, $itemImage);

        $this->assertFalse($result);
    }
}
