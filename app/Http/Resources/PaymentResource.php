<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Resolve customer: direct relationship first, then via order, then Walk-in
        $customer = $this->customer ?? $this->order?->customer;
        $customerName = $customer?->name;
        if ($customerName === null && $this->order) {
            $customerName = 'Walk-in';
        }

        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'order_id' => $this->order_id,
            'order_number' => $this->order?->order_number,
            'customer_id' => $this->customer_id ?? $this->order?->customer_id,
            'customer' => $customerName,
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
