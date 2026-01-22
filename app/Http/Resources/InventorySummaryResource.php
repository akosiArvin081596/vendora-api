<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventorySummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_items' => $this->resource['total_items'],
            'low_stock_items' => $this->resource['low_stock_items'],
            'out_of_stock_items' => $this->resource['out_of_stock_items'],
        ];
    }
}
