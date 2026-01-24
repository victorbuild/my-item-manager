<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * 測試：成功建立分類
     */
    #[Test]
    public function it_should_create_category_successfully(): void
    {
        // Arrange
        $categoryData = ['name' => '測試分類'];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/categories', $categoryData);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '成功建立分類',
            ])
            ->assertJsonStructure([
                'items' => [
                    '*' => ['id', 'name', 'created_at', 'updated_at']
                ]
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => '測試分類',
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * 測試：驗證失敗時返回 422
     */
    #[Test]
    public function it_should_return_422_when_validation_fails(): void
    {
        // Arrange
        $categoryData = []; // 缺少必填欄位

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/categories', $categoryData);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * 測試：分類名稱重複時返回 422
     */
    #[Test]
    public function it_should_return_422_when_category_name_already_exists(): void
    {
        // Arrange
        $existingCategory = Category::create([
            'name' => '已存在的分類',
            'user_id' => $this->user->id,
        ]);

        $categoryData = ['name' => '已存在的分類'];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/categories', $categoryData);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['name']);

        $response->assertJsonFragment([
            'name' => ['此分類名稱已存在，請使用其他名稱']
        ]);
    }

    /**
     * 測試：不同使用者可以使用相同的分類名稱
     */
    #[Test]
    public function it_should_allow_same_category_name_for_different_users(): void
    {
        // Arrange
        $userB = User::factory()->create();
        Category::create([
            'name' => '相同名稱',
            'user_id' => $userB->id,
        ]);

        $categoryData = ['name' => '相同名稱'];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/categories', $categoryData);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '成功建立分類',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => '相同名稱',
            'user_id' => $this->user->id,
        ]);
    }
}
