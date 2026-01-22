<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPaymentMethodsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'start_date' => $this->resource['start_date'],
            'end_date' => $this->resource['end_date'],
            'total_amount' => $this->resource['total_amount'],
            'methods' => $this->resource['methods'],
        ];
    }
}
