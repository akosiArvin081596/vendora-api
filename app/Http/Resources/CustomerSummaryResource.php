<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_customers' => $this->resource['total_customers'],
            'active' => $this->resource['active'],
            'vip' => $this->resource['vip'],
            'inactive' => $this->resource['inactive'],
        ];
    }
}
