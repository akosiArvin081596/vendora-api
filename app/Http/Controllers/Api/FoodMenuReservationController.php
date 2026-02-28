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

class FoodMenuReservationController extends Controller
{
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

    public function show(Request $request, int $reservation): FoodMenuReservationResource
    {
        $reservation = $this->findReservation($request, $reservation);

        return new FoodMenuReservationResource($reservation->load('foodMenuItem'));
    }

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
