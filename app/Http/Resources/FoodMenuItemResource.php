<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FoodMenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price' => $this->price !== null ? round($this->price / 100, 2) : null,
            'currency' => $this->currency,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'total_servings' => $this->total_servings,
            'reserved_servings' => $this->reserved_servings,
            'remaining_servings' => $this->remainingServings(),
            'is_sold_out' => $this->isSoldOut(),
            'is_available' => $this->is_available,
            'reservations' => FoodMenuReservationResource::collection($this->whenLoaded('reservations')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
