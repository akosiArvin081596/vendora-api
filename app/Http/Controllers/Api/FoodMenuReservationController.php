<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFoodMenuReservationRequest;
use App\Http\Requests\UpdateFoodMenuReservationRequest;
use App\Http\Resources\FoodMenuReservationResource;
use App\Models\FoodMenuItem;
use App\Models\FoodMenuReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class FoodMenuReservationController extends Controller
{
    #[OA\Get(
        path: '/api/food-menu-reservations',
        tags: ['Food Menu Reservations'],
        summary: 'List reservations',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'cancelled', 'completed'])),
            new OA\Parameter(name: 'food_menu_item_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reservations list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FoodMenuReservation')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FoodMenuReservation::query()
            ->where('user_id', $request->user()->id)
            ->with('foodMenuItem');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('food_menu_item_id')) {
            $query->where('food_menu_item_id', $request->integer('food_menu_item_id'));
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        return FoodMenuReservationResource::collection(
            $query->latest()->paginate($perPage)
        );
    }

    #[OA\Post(
        path: '/api/food-menu-reservations',
        tags: ['Food Menu Reservations'],
        summary: 'Create a reservation',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['food_menu_item_id', 'customer_name', 'servings'],
                properties: [
                    new OA\Property(property: 'food_menu_item_id', type: 'integer', example: 1),
                    new OA\Property(property: 'customer_id', type: 'integer', example: 5),
                    new OA\Property(property: 'customer_name', type: 'string', example: 'Juan Dela Cruz'),
                    new OA\Property(property: 'customer_phone', type: 'string', example: '09171234567'),
                    new OA\Property(property: 'servings', type: 'integer', example: 3),
                    new OA\Property(property: 'notes', type: 'string', example: 'Extra rice'),
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
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error or insufficient servings'),
        ]
    )]
    public function store(StoreFoodMenuReservationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $reservation = DB::transaction(function () use ($data, $request) {
            $menuItem = FoodMenuItem::query()
                ->where('id', $data['food_menu_item_id'])
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = $menuItem->total_servings - $menuItem->reserved_servings;

            if ($data['servings'] > $remaining) {
                abort(422, 'Insufficient servings available. Only '.$remaining.' remaining.');
            }

            $menuItem->increment('reserved_servings', $data['servings']);

            return FoodMenuReservation::query()->create([
                'food_menu_item_id' => $menuItem->id,
                'user_id' => $request->user()->id,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'servings' => $data['servings'],
                'status' => ReservationStatus::Pending,
                'notes' => $data['notes'] ?? null,
                'reserved_at' => $data['reserved_at'] ?? null,
            ]);
        });

        return (new FoodMenuReservationResource($reservation->load('foodMenuItem')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/food-menu-reservations/{reservation}',
        tags: ['Food Menu Reservations'],
        summary: 'Get a single reservation',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reservation details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/FoodMenuReservation'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, int $reservation): FoodMenuReservationResource
    {
        $reservation = $this->findReservation($request, $reservation);

        return new FoodMenuReservationResource($reservation->load('foodMenuItem'));
    }

    #[OA\Put(
        path: '/api/food-menu-reservations/{reservation}',
        tags: ['Food Menu Reservations'],
        summary: 'Update a reservation',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'customer_name', type: 'string', example: 'Maria Santos'),
                    new OA\Property(property: 'customer_phone', type: 'string', example: '09281234567'),
                    new OA\Property(property: 'servings', type: 'integer', example: 5),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'cancelled', 'completed'], example: 'confirmed'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Updated notes'),
                    new OA\Property(property: 'reserved_at', type: 'string', format: 'date-time', example: '2026-03-07 14:00:00'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reservation updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/FoodMenuReservation'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error or insufficient servings'),
        ]
    )]
    public function update(UpdateFoodMenuReservationRequest $request, int $reservation): FoodMenuReservationResource
    {
        $reservation = $this->findReservation($request, $reservation);
        $data = $request->validated();

        DB::transaction(function () use ($reservation, $data) {
            $menuItem = FoodMenuItem::query()
                ->where('id', $reservation->food_menu_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $reservation->status;
            $newStatus = isset($data['status']) ? ReservationStatus::from($data['status']) : $oldStatus;

            $oldServings = $reservation->servings;
            $newServings = $data['servings'] ?? $oldServings;

            $wasCancelled = $oldStatus === ReservationStatus::Cancelled;
            $isCancelling = $newStatus === ReservationStatus::Cancelled;

            if ($wasCancelled && ! $isCancelling) {
                $remaining = $menuItem->total_servings - $menuItem->reserved_servings;
                if ($newServings > $remaining) {
                    abort(422, 'Insufficient servings available. Only '.$remaining.' remaining.');
                }
                $menuItem->increment('reserved_servings', $newServings);
            } elseif (! $wasCancelled && $isCancelling) {
                $menuItem->decrement('reserved_servings', $oldServings);
            } elseif (! $wasCancelled && ! $isCancelling && $newServings !== $oldServings) {
                $delta = $newServings - $oldServings;
                if ($delta > 0) {
                    $remaining = $menuItem->total_servings - $menuItem->reserved_servings;
                    if ($delta > $remaining) {
                        abort(422, 'Insufficient servings available. Only '.$remaining.' remaining.');
                    }
                    $menuItem->increment('reserved_servings', $delta);
                } else {
                    $menuItem->decrement('reserved_servings', abs($delta));
                }
            }

            $reservation->update($data);
        });

        return new FoodMenuReservationResource($reservation->fresh()->load('foodMenuItem'));
    }

    #[OA\Delete(
        path: '/api/food-menu-reservations/{reservation}',
        tags: ['Food Menu Reservations'],
        summary: 'Delete a reservation',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Reservation deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, int $reservation): JsonResponse
    {
        $reservation = $this->findReservation($request, $reservation);

        DB::transaction(function () use ($reservation) {
            if ($reservation->status !== ReservationStatus::Cancelled) {
                $menuItem = FoodMenuItem::query()
                    ->where('id', $reservation->food_menu_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $menuItem->decrement('reserved_servings', $reservation->servings);
            }

            $reservation->delete();
        });

        return response()->json(null, 204);
    }

    protected function findReservation(Request $request, int $id): FoodMenuReservation
    {
        return FoodMenuReservation::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }
}
