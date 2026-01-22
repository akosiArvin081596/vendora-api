<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'user_role' => $this->user_role ?? null,
            'orders_count' => $this->when(isset($this->orders_count), $this->orders_count),
            'customers_count' => $this->when(isset($this->customers_count), $this->customers_count),
            'store_products_count' => $this->when(isset($this->store_products_count), $this->store_products_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
