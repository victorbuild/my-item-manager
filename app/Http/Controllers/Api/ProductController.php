<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('q');
        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = min(max($perPage, 1), 100);

        $products = $this->productRepository->paginateForUser($request->user()->id, $search, $perPage);

        return response()->json((new ProductCollection($products))->toArray($request));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $product = $this->productRepository->create($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => '成功建立產品',
            'data' => new ProductResource($product),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateProductRequest $request, string $shortId): JsonResponse
    {
        $product = $this->productRepository->findByShortIdOrFail($shortId);

        $this->authorize('update', $product);

        $validated = $request->validated();

        $product = $this->productRepository->update($product, $validated);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * 取得產品詳情（包含輕量統計）
     *
     * @param Request $request
     * @param string $shortId
     * @return JsonResponse
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 找不到產品時拋出
     * @throws \Illuminate\Auth\Access\AuthorizationException 非擁有者時拋出（403）
     */
    public function show(Request $request, string $shortId): JsonResponse
    {
        $product = $this->productRepository->findByShortIdOrFail($shortId);

        $this->authorize('view', $product);

        $product->load(['category']);

        $stats = $this->productRepository->getItemStatusCounts($request->user()->id, $product->id);

        return ApiResponse::success([
            ...(new ProductResource($product))->toArray($request),
            'stats' => $stats,
        ]);
    }

    /**
     * 刪除產品
     *
     * @param string $shortId
     * @return Response
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 找不到產品時拋出
     * @throws \Illuminate\Auth\Access\AuthorizationException 非擁有者時拋出（403）
     */
    public function destroy(string $shortId): Response
    {
        $product = $this->productRepository->findByShortIdOrFail($shortId);

        $this->authorize('delete', $product);

        $this->productService->deleteOrFailIfNoItems($product);

        return response()->noContent();
    }
}
