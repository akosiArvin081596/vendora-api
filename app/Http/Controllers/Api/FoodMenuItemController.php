<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicFoodMenuReservationRequest;
use App\Http\Requests\StoreFoodMenuItemRequest;
use App\Http\Requests\UpdateFoodMenuItemRequest;
use App\Http\Resources\FoodMenuItemResource;
use App\Http\Resources\FoodMenuReservationResource;
use App\Models\FoodMenuItem;
use App\Models\FoodMenuReservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class FoodMenuItemController extends Controller
{
    #[OA\Get(
        path: '/api/food-menu',
        tags: ['Food Menu'],
        summary: 'List own food menu items',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_available', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'store_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Food menu items list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FoodMenuItem')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FoodMenuItem::query()
            ->where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->value());
        }

        if ($request->has('is_available')) {
            $query->where('is_available', $request->boolean('is_available'));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        return FoodMenuItemResource::collection(
            $query->latest()->paginate($perPage)
        );
    }

    #[OA\Post(
        path: '/api/food-menu',
        tags: ['Food Menu'],
        summary: 'Create a food menu item',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price', 'total_servings'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Chicken Adobo'),
                    new OA\Property(property: 'description', type: 'string', example: 'Classic Filipino chicken adobo'),
                    new OA\Property(property: 'category', type: 'string', example: 'Main Course'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 120.00),
                    new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                    new OA\Property(property: 'image_base64', type: 'string', example: 'data:image/png;base64,...'),
                    new OA\Property(property: 'total_servings', type: 'integer', example: 50),
                    new OA\Property(property: 'is_available', type: 'boolean', example: true),
                    new OA\Property(property: 'store_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Menu item created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/FoodMenuItem'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreFoodMenuItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['user_id'] = $request->user()->id;
        $data['price'] = $this->normalizeMoney($request->input('price'));

        if (! empty($data['image_base64'])) {
            $data['image'] = $this->saveBase64Image($data['image_base64']);
        } elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('food-menu', 'public');
        }

        unset($data['image_base64']);

        $menuItem = FoodMenuItem::query()->create($data);

        return (new FoodMenuItemResource($menuItem))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/food-menu/{foodMenuItem}',
        tags: ['Food Menu'],
        summary: 'Get a single food menu item',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'foodMenuItem', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menu item details with reservations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/FoodMenuItem'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, int $foodMenuItem): FoodMenuItemResource
    {
        $item = $this->findMenuItem($request, $foodMenuItem);

        return new FoodMenuItemResource($item->load('reservations'));
    }

    #[OA\Put(
        path: '/api/food-menu/{foodMenuItem}',
        tags: ['Food Menu'],
        summary: 'Update a food menu item',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'foodMenuItem', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Chicken Adobo'),
                    new OA\Property(property: 'description', type: 'string', example: 'Updated description'),
                    new OA\Property(property: 'category', type: 'string', example: 'Main Course'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 150.00),
                    new OA\Property(property: 'currency', type: 'string', example: 'PHP'),
                    new OA\Property(property: 'image_base64', type: 'string', example: 'data:image/png;base64,...'),
                    new OA\Property(property: 'total_servings', type: 'integer', example: 100),
                    new OA\Property(property: 'is_available', type: 'boolean', example: true),
                    new OA\Property(property: 'store_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menu item updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/FoodMenuItem'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateFoodMenuItemRequest $request, int $foodMenuItem): FoodMenuItemResource
    {
        $item = $this->findMenuItem($request, $foodMenuItem);
        $data = $request->validated();

        if (array_key_exists('price', $data)) {
            $data['price'] = $this->normalizeMoney($request->input('price'));
        }

        if (! empty($data['image_base64'])) {
            $data['image'] = $this->saveBase64Image($data['image_base64']);
        } elseif ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('food-menu', 'public');
        } else {
            unset($data['image']);
        }

        unset($data['image_base64']);

        $item->update($data);

        return new FoodMenuItemResource($item);
    }

    #[OA\Delete(
        path: '/api/food-menu/{foodMenuItem}',
        tags: ['Food Menu'],
        summary: 'Delete a food menu item',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'foodMenuItem', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Menu item deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, int $foodMenuItem): JsonResponse
    {
        $item = $this->findMenuItem($request, $foodMenuItem);
        $item->delete();

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/food-menu/public/vendors',
        tags: ['Food Menu (Public)'],
        summary: 'List vendors with available food menus',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Vendor list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Juan\'s Kitchen'),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function publicVendors(): JsonResponse
    {
        $vendors = User::query()
            ->whereHas('foodMenuItems', function ($q) {
                $q->where('is_available', true)
                    ->whereColumn('reserved_servings', '<', 'total_servings');
            })
            ->with('vendorProfile')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->vendorProfile?->business_name ?? $user->name,
            ]);

        return response()->json(['success' => true, 'data' => $vendors]);
    }

    #[OA\Get(
        path: '/api/food-menu/public/{user}',
        tags: ['Food Menu (Public)'],
        summary: 'View a vendor\'s public food menu',
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, description: 'Vendor user ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Public menu items',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FoodMenuItem')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Vendor not found'),
        ]
    )]
    public function publicMenu(Request $request, User $user): AnonymousResourceCollection
    {
        $query = FoodMenuItem::query()
            ->where('user_id', $user->id)
            ->where('is_available', true)
            ->whereColumn('reserved_servings', '<', 'total_servings');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->value());
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        return FoodMenuItemResource::collection(
            $query->latest()->paginate($perPage)
        );
    }

    #[OA\Post(
        path: '/api/food-menu/public/{user}/reserve',
        tags: ['Food Menu (Public)'],
        summary: 'Create a public reservation',
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, description: 'Vendor user ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['food_menu_item_id', 'customer_name', 'customer_phone', 'servings'],
                properties: [
                    new OA\Property(property: 'food_menu_item_id', type: 'integer', example: 1),
                    new OA\Property(property: 'customer_name', type: 'string', example: 'Maria Santos'),
                    new OA\Property(property: 'customer_phone', type: 'string', example: '09171234567'),
                    new OA\Property(property: 'servings', type: 'integer', example: 5),
                    new OA\Property(property: 'notes', type: 'string', example: 'No spicy please'),
                    new OA\Property(property: 'reserved_at', type: 'string', format: 'date-time', example: '2026-03-07 12:00:00'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Reservation created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/FoodMenuReservation'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Vendor not found'),
            new OA\Response(response: 422, description: 'Validation error or insufficient servings'),
        ]
    )]
    public function publicReserve(PublicFoodMenuReservationRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $reservation = DB::transaction(function () use ($data, $user) {
            $menuItem = FoodMenuItem::query()
                ->where('id', $data['food_menu_item_id'])
                ->where('user_id', $user->id)
                ->where('is_available', true)
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = $menuItem->total_servings - $menuItem->reserved_servings;

            if ($data['servings'] > $remaining) {
                abort(422, 'Insufficient servings available. Only '.$remaining.' remaining.');
            }

            $menuItem->increment('reserved_servings', $data['servings']);

            return FoodMenuReservation::query()->create([
                'food_menu_item_id' => $menuItem->id,
                'user_id' => $user->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'servings' => $data['servings'],
                'status' => ReservationStatus::Pending,
                'notes' => $data['notes'] ?? null,
                'reserved_at' => $data['reserved_at'] ?? null,
            ]);
        });

        return (new FoodMenuReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    protected function findMenuItem(Request $request, int $id): FoodMenuItem
    {
        return FoodMenuItem::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    protected function normalizeMoney(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    protected function saveBase64Image(string $base64): string
    {
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $imageData = base64_decode($base64, true);

        if ($imageData === false) {
            abort(422, 'Invalid base64 image data.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($imageData);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $ext = $allowedMimes[$mime] ?? 'jpg';
        $filename = 'food-menu/'.uniqid('menu_', true).'.'.$ext;

        Storage::disk('public')->put($filename, $imageData);

        return $filename;
    }
}
