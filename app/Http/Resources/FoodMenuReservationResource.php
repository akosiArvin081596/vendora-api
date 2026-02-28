<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodMenuReservationResource extends JsonResource
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
            'food_menu_item_id' => $this->food_menu_item_id,
            'user_id' => $this->user_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'servings' => $this->servings,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'reserved_at' => $this->reserved_at?->toISOString(),
            'food_menu_item' => new FoodMenuItemResource($this->whenLoaded('foodMenuItem')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
