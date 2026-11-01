<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Product;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_should_reject_foreign_resources_when_a_verified_user_changes_resource_ids(): void
    {
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $category = Category::create(['name' => '私人分類', 'user_id' => $owner->id]);
        $product = Product::factory()->create(['user_id' => $owner->id, 'category_id' => $category->id]);
        $item = Item::factory()->create(['user_id' => $owner->id, 'product_id' => $product->id]);
        $image = ItemImage::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($visitor);
        $urls = [
            '/api/items/' . $item->short_id,
            '/api/products/' . $product->short_id,
            '/api/categories/' . $category->id,
            '/api/media/' . $image->uuid,
        ];
        foreach ($urls as $url) {
            $this->getJson($url)->assertForbidden();
            $this->deleteJson($url)->assertForbidden();
        }
        $this->putJson('/api/items/' . $item->short_id, ['name' => '越權修改'])->assertForbidden();
        $this->putJson('/api/products/' . $product->short_id, ['name' => '越權修改'])->assertForbidden();
        $this->putJson('/api/categories/' . $category->id, ['name' => '越權修改'])->assertForbidden();
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => $item->name]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => $product->name]);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => $category->name]);
        $this->assertDatabaseHas('item_images', ['uuid' => $image->uuid]);
    }

    #[Test]
    public function it_should_use_only_the_current_session_when_client_identity_claims_are_forged(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->getJson('/api/user?user_id=' . $other->id . '&loggedIn=true')->assertUnauthorized();
        $this->actingAs($user)->getJson('/api/user?user_id=' . $other->id)
            ->assertOk()->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
    }

    #[Test]
    public function it_should_not_reuse_the_previous_account_when_logging_in_after_logout(): void
    {
        $this->mock(RecaptchaService::class)->shouldReceive('verify')->andReturn(true);
        $first = User::factory()->create(['password' => 'password123']);
        $second = User::factory()->unverified()->create(['password' => 'password123']);
        foreach ([$first, $second] as $user) {
            $this->app['auth']->forgetGuards();
            $this->app['auth']->shouldUse('web');
            $credentials = ['email' => $user->email, 'password' => 'password123', 'recaptcha_token' => 'test'];
            $this->postJson('/login', $credentials)
                ->assertOk();
            $this->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id)
                ->assertJsonPath('data.email_verified_at', $user->email_verified_at?->toJSON());
            $this->postJson('/logout')->assertOk();
            $this->assertGuest('web');
            $this->app['auth']->forgetGuards();
            $this->app['auth']->shouldUse('web');
            $this->getJson('/api/user')->assertUnauthorized();
        }
    }
}
