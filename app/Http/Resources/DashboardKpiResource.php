<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardKpiResource extends JsonResource
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
            'total_sales' => $this->resource['total_sales'],
            'total_orders' => $this->resource['total_orders'],
            'net_revenue' => $this->resource['net_revenue'],
            'average_order_value' => $this->resource['average_order_value'],
            'items_sold' => $this->resource['items_sold'],
            'currency' => $this->resource['currency'],
        ];
    }
}
