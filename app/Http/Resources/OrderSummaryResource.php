<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_orders' => $this->resource['total_orders'],
            'pending' => $this->resource['pending'],
            'processing' => $this->resource['processing'],
            'completed' => $this->resource['completed'],
        ];
    }
}
