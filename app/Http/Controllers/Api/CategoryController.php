<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/categories',
        tags: ['Category'],
        summary: 'List categories with optional filters (public endpoint)',
        description: 'Public endpoint for e-commerce browsing. Returns all active categories by default.',
        parameters: [
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'with_count', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Grocery'),
                                    new OA\Property(property: 'slug', type: 'string', example: 'grocery'),
                                    new OA\Property(property: 'description', type: 'string', example: 'Food items'),
                                    new OA\Property(property: 'icon', type: 'string', example: 'shopping-cart'),
                                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                    new OA\Property(property: 'product_count', type: 'integer', example: 25),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query();

        if ($request->filled('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        if (filter_var($request->input('with_count'), FILTER_VALIDATE_BOOLEAN)) {
            $query->withCount('products');
        }

        $categories = $query->orderBy('name')->get();

        return CategoryResource::collection($categories);
    }

    #[OA\Post(
        path: '/api/categories',
        tags: ['Category'],
        summary: 'Create a category',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Electronics'),
                    new OA\Property(property: 'description', type: 'string', example: 'Electronic devices'),
                    new OA\Property(property: 'icon', type: 'string', example: 'cpu'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/categories/{category}',
        tags: ['Category'],
        summary: 'Get a single category (public endpoint)',
        description: 'Public endpoint to view category details.',
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Category details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $category): CategoryResource
    {
        $category = Category::query()->withCount('products')->findOrFail($category);

        return new CategoryResource($category);
    }

    #[OA\Patch(
        path: '/api/categories/{category}',
        tags: ['Category'],
        summary: 'Update a category',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Electronics'),
                    new OA\Property(property: 'description', type: 'string', example: 'Electronic devices'),
                    new OA\Property(property: 'icon', type: 'string', example: 'cpu'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateCategoryRequest $request, int $category): CategoryResource
    {
        $category = Category::query()->findOrFail($category);
        $category->update($request->validated());

        return new CategoryResource($category->loadCount('products'));
    }

    #[OA\Delete(
        path: '/api/categories/{category}',
        tags: ['Category'],
        summary: 'Delete a category',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 409, description: 'Cannot delete - has products'),
        ]
    )]
    public function destroy(int $category): Response|JsonResponse
    {
        $category = Category::query()->withCount('products')->findOrFail($category);

        if ($category->products_count > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated products.',
                'product_count' => $category->products_count,
            ], 409);
        }

        $category->delete();

        return response()->noContent();
    }
}
