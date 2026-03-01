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

class FoodMenuItemController extends Controller
{
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

    public function show(Request $request, int $foodMenuItem): FoodMenuItemResource
    {
        $item = $this->findMenuItem($request, $foodMenuItem);

        return new FoodMenuItemResource($item->load('reservations'));
    }

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

    public function destroy(Request $request, int $foodMenuItem): JsonResponse
    {
        $item = $this->findMenuItem($request, $foodMenuItem);
        $item->delete();

        return response()->json(null, 204);
    }

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
