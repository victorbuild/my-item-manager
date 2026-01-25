<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * @group Categories 分類管理
 *
 * 分類（Category）API ，用於管理使用者的產品分類。
 */
class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService,
        private CategoryRepository $categoryRepository
    ) {
    }

    /**
     * 取得分類列表
     *
     * 取得當前使用者的所有分類列表，支援分頁、搜尋和取得全部資料（用於下拉選單）。
     *
     * @queryParam all string 是否取得所有分類（不分頁）。用於下拉選單等場景。範例: true
     * @queryParam page integer 頁碼（當 all 不為 true 時有效）。預設值: 1
     * @queryParam per_page integer 每頁筆數（當 all 不為 true 時有效）。預設值: 10
     * @queryParam q string 搜尋關鍵字（當 all 不為 true 時有效）。會搜尋分類名稱。範例: 電子
     *
     * @response 200 {
     *   "success": true,
     *   "message": "取得成功",
     *   "items": [
     *     {
     *       "id": 1,
     *       "name": "電子產品",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 10,
     *     "total": 1
     *   }
     * }
     *
     * @responseField items array 分類列表
     * @responseField items[].id integer 分類 ID
     * @responseField items[].name string 分類名稱
     * @responseField items[].created_at string 建立時間（ISO 8601）
     * @responseField items[].updated_at string 更新時間（ISO 8601）
     * @responseField meta object 分頁資訊（當 all 不為 true 時提供）
     * @responseField meta.current_page integer 當前頁碼
     * @responseField meta.last_page integer 最後一頁
     * @responseField meta.per_page integer 每頁筆數
     * @responseField meta.total integer 總筆數
     */
    public function index(Request $request): JsonResponse
    {
        // 如果請求所有分類（用於下拉選單），不分頁
        if ($request->query('all') === 'true' || $request->query('all') === '1') {
            $categories = $this->categoryService->getAll($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => '取得成功',
                'items' => CategoryResource::collection($categories),
            ]);
        }

        // 否則使用分頁
        $perPage = (int) ($request->query('per_page') ?? 10);
        $page = (int) ($request->query('page') ?? 1);
        $search = $request->query('q');

        $result = $this->categoryService->getAllPaginated($request->user()->id, $page, $perPage, $search);

        return response()->json([
            'success' => true,
            'message' => '取得成功',
            'items' => CategoryResource::collection($result['items']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 建立新分類
     *
     * 為當前使用者建立一個新的分類。分類名稱在同一使用者下必須唯一。
     *
     * @bodyParam name string required 分類名稱。最大長度 255 字元，且在同一使用者下必須唯一。範例: 電子產品
     *
     * @response 201 {
     *   "success": true,
     *   "message": "成功建立分類",
     *   "items": [
     *     {
     *       "id": 1,
     *       "name": "電子產品",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ]
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "驗證失敗",
     *   "errors": {
     *     "name": [
     *       "分類名稱為必填欄位",
     *       "此分類名稱已存在，請使用其他名稱"
     *     ]
     *   }
     * }
     *
     * @responseField items array 建立的分類資料
     * @responseField items[].id integer 分類 ID
     * @responseField items[].name string 分類名稱
     * @responseField items[].created_at string 建立時間（ISO 8601）
     * @responseField items[].updated_at string 更新時間（ISO 8601）
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = $this->categoryRepository->create($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => '成功建立分類',
            'items' => [new CategoryResource($category)],
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新分類
     *
     * 更新指定分類的資訊。只能更新屬於當前使用者的分類。
     *
     * @urlParam category integer required 分類 ID。範例: 1
     *
     * @bodyParam name string required 分類名稱。最大長度 255 字元，且在同一使用者下必須唯一（排除當前分類）。範例: 電子產品
     *
     * @response 200 {
     *   "success": true,
     *   "message": "更新成功",
     *   "items": [
     *     {
     *       "id": 1,
     *       "name": "電子產品（已更新）",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T01:00:00.000000Z"
     *     }
     *   ]
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "找不到指定的分類"
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "驗證失敗",
     *   "errors": {
     *     "name": [
     *       "此分類名稱已存在，請使用其他名稱"
     *     ]
     *   }
     * }
     *
     * @responseField items array 更新後的分類資料
     * @responseField items[].id integer 分類 ID
     * @responseField items[].name string 分類名稱
     * @responseField items[].created_at string 建立時間（ISO 8601）
     * @responseField items[].updated_at string 更新時間（ISO 8601）
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        // 使用 Policy 檢查權限
        $this->authorize('update', $category);

        $validated = $request->validated();
        $categoryModel = $this->categoryService->update($category, $validated);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'items' => [new CategoryResource($categoryModel)],
        ]);
    }

    /**
     * 刪除分類
     *
     * 刪除指定的分類。只能刪除屬於當前使用者的分類，且該分類下不能有任何產品，否則會回傳錯誤。
     *
     * @urlParam category integer required 分類 ID。範例: 1
     *
     * @response 204
     * @response 404 {
     *   "success": false,
     *   "message": "找不到指定的分類"
     * }
     * @response 422 {
     *   "success": false,
     *   "message": "無法刪除此分類，因為還有 5 個產品關聯此分類。"
     * }
     */
    public function destroy(Request $request, Category $category): JsonResponse|Response
    {
        // 使用 Policy 檢查權限
        $this->authorize('delete', $category);

        try {
            $this->categoryService->delete($category);

            return response()->noContent();
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 取得分類詳情
     *
     * 取得指定分類的詳細資訊，包含該分類下的產品列表、統計資料（產品數量、物品數量、各狀態物品數量等）。
     *
     * @urlParam category integer required 分類 ID。範例: 1
     *
     * @queryParam page integer 產品列表的頁碼。預設值: 1
     * @queryParam per_page integer 每頁產品筆數。預設值: 10
     *
     * @response 200 {
     *   "success": true,
     *   "message": "資料載入成功",
     *   "items": [
     *     {
     *       "id": 1,
     *       "name": "電子產品",
     *       "created_at": "2024-01-01T00:00:00.000000Z",
     *       "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     *   ],
     *   "stats": {
     *     "products_count": 10,
     *     "items_count": 25,
     *     "items_in_use": 5,
     *     "items_unused": 15,
     *     "items_pre_arrival": 3,
     *     "items_discarded": 2
     *   },
     *   "products": [
     *     {
     *       "id": 1,
     *       "short_id": "PRD001",
     *       "name": "iPhone 15",
     *       "brand": "Apple",
     *       "items_count": 3,
     *       "status_counts": {
     *         "pre_arrival": 0,
     *         "unused": 2,
     *         "in_use": 1,
     *         "discarded": 0
     *       }
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 10,
     *     "total": 10
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "找不到指定的分類"
     * }
     *
     * @responseField items array 分類資料
     * @responseField items[].id integer 分類 ID
     * @responseField items[].name string 分類名稱
     * @responseField stats object 統計資料
     * @responseField stats.products_count integer 產品總數
     * @responseField stats.items_count integer 物品總數
     * @responseField stats.items_in_use integer 使用中的物品數量
     * @responseField stats.items_unused integer 未使用的物品數量
     * @responseField stats.items_pre_arrival integer 尚未到貨的物品數量
     * @responseField stats.items_discarded integer 已丟棄的物品數量
     * @responseField products array 產品列表（分頁）
     * @responseField products[].id integer 產品 ID
     * @responseField products[].short_id string 產品簡短 ID
     * @responseField products[].name string 產品名稱
     * @responseField products[].brand string 產品品牌
     * @responseField products[].items_count integer 該產品的物品數量
     * @responseField products[].status_counts object 該產品的各狀態物品數量
     * @responseField products[].status_counts.pre_arrival integer 尚未到貨
     * @responseField products[].status_counts.unused integer 未使用
     * @responseField products[].status_counts.in_use integer 使用中
     * @responseField products[].status_counts.discarded integer 已丟棄
     * @responseField meta object 產品列表的分頁資訊
     * @responseField meta.current_page integer 當前頁碼
     * @responseField meta.last_page integer 最後一頁
     * @responseField meta.per_page integer 每頁筆數
     * @responseField meta.total integer 總筆數
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        // 使用 Policy 檢查權限
        $this->authorize('view', $category);

        $perPage = (int) ($request->query('per_page') ?? 10);
        $page = (int) ($request->query('page') ?? 1);

        $result = $this->categoryService->getCategoryWithStats($category, $page, $perPage);

        return response()->json([
            'success' => true,
            'message' => '資料載入成功',
            'items' => [new CategoryResource($result['category'])],
            'stats' => $result['stats'],
            'products' => $result['products'],
            'meta' => $result['meta'],
        ]);
    }
}
