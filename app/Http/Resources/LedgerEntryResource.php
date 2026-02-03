<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
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
            'type' => $this->type,
            'category' => $this->category,
            'product' => $this->product?->name,
            'product_id' => $this->product_id,
            'order_id' => $this->order_id,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'balance_qty' => $this->balance_qty,
            'balance_amount' => $this->balance_amount,
            'reference' => $this->reference,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
