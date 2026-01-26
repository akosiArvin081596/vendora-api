<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lowStockThreshold = $this->min_stock ?? 20;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'has_barcode' => $this->hasBarcode(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'price' => $this->price !== null ? (int) ($this->price / 100) : null,
            'cost' => $this->cost !== null ? (int) ($this->cost / 100) : null,
            'currency' => $this->currency,
            'unit' => $this->unit ?? 'pc',
            'stock' => $this->stock,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'is_low_stock' => $this->stock <= $lowStockThreshold,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'is_active' => $this->is_active ?? true,
            'is_ecommerce' => $this->is_ecommerce ?? true,
            'bulk_pricing' => ProductBulkPriceResource::collection($this->whenLoaded('bulkPrices')),
            'vendor' => $this->when($this->relationLoaded('user'), function () {
                $user = $this->user;

                return [
                    'id' => $user->id,
                    'name' => $user->vendorProfile?->business_name ?? $user->name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
