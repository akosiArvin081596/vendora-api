<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vatableSales = $this->total > 0 ? (int) round($this->total / 1.12) : 0;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => $this->customer?->name,
            'customer_id' => $this->customer_id,
            'ordered_at' => $this->ordered_at?->toDateString(),
            'items_count' => $this->items_count,
            'total' => $this->total,
            'vatable_sales' => $vatableSales,
            'vat_amount' => $this->total - $vatableSales,
            'currency' => $this->currency,
            'status' => $this->status,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
