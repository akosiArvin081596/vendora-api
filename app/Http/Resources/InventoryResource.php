<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
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
            'sku' => $this->sku,
            'stock' => $this->stock,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'status' => $this->status(),
        ];
    }

    protected function status(): string
    {
        if ($this->stock === 0) {
            return 'out_of_stock';
        }

        if ($this->stock <= 2) {
            return 'critical';
        }

        if ($this->min_stock !== null && $this->stock <= $this->min_stock) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
