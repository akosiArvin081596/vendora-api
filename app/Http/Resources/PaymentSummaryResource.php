<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_revenue' => $this->resource['total_revenue'],
            'cash_payments' => $this->resource['cash_payments'],
            'card_payments' => $this->resource['card_payments'],
            'online_payments' => $this->resource['online_payments'],
        ];
    }
}
