<?php

namespace App\Services;

use App\Exceptions\InsufficientCostLayersException;
use App\Models\CostLayerConsumption;
use App\Models\InventoryCostLayer;
use App\Models\Product;

class FifoCostService
{
    /**
     * Create a new cost layer when stock is added.
     *
     * @param  array{product_id: int, user_id: int, quantity: int, unit_cost: int, inventory_adjustment_id?: int|null, reference?: string|null, acquired_at?: \DateTimeInterface|null}  $params
     */
    public function createLayer(array $params): InventoryCostLayer
    {
        return InventoryCostLayer::query()->create([
            'product_id' => $params['product_id'],
            'user_id' => $params['user_id'],
            'inventory_adjustment_id' => $params['inventory_adjustment_id'] ?? null,
            'quantity' => $params['quantity'],
            'remaining_quantity' => $params['quantity'],
            'unit_cost' => $params['unit_cost'],
            'acquired_at' => $params['acquired_at'] ?? now(),
            'reference' => $params['reference'] ?? null,
        ]);
    }

    /**
     * Consume cost layers using FIFO ordering.
     *
     * @param  array{product_id: int, quantity: int, order_item_id?: int|null, inventory_adjustment_id?: int|null}  $params
     * @return array{consumptions: list<CostLayerConsumption>, total_cost: int, weighted_average_cost: int}
     */
    public function consumeLayers(array $params): array
    {
        $productId = $params['product_id'];
        $quantityNeeded = $params['quantity'];
        $orderItemId = $params['order_item_id'] ?? null;
        $adjustmentId = $params['inventory_adjustment_id'] ?? null;

        $layers = InventoryCostLayer::query()
            ->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('acquired_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $totalAvailable = $layers->sum('remaining_quantity');
        if ($totalAvailable < $quantityNeeded) {
            throw new InsufficientCostLayersException($productId, $quantityNeeded, $totalAvailable);
        }

        $remaining = $quantityNeeded;
        $totalCost = 0;
        $consumptions = [];

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $consume = min($remaining, $layer->remaining_quantity);
            $layer->decrement('remaining_quantity', $consume);

            $consumption = CostLayerConsumption::query()->create([
                'cost_layer_id' => $layer->id,
                'order_item_id' => $orderItemId,
                'inventory_adjustment_id' => $adjustmentId,
                'quantity_consumed' => $consume,
                'unit_cost' => $layer->unit_cost,
            ]);

            $consumptions[] = $consumption;
            $totalCost += $consume * $layer->unit_cost;
            $remaining -= $consume;
        }

        $weightedAverageCost = $quantityNeeded > 0
            ? (int) round($totalCost / $quantityNeeded)
            : 0;

        return [
            'consumptions' => $consumptions,
            'total_cost' => $totalCost,
            'weighted_average_cost' => $weightedAverageCost,
        ];
    }

    /**
     * Consume from a specific cost layer (for targeted writeoffs like damaged batches).
     *
     * @param  array{cost_layer_id: int, quantity: int, inventory_adjustment_id?: int|null}  $params
     * @return array{consumptions: list<CostLayerConsumption>, total_cost: int, weighted_average_cost: int}
     */
    public function consumeSpecificLayer(array $params): array
    {
        $layer = InventoryCostLayer::query()
            ->where('id', $params['cost_layer_id'])
            ->where('remaining_quantity', '>', 0)
            ->lockForUpdate()
            ->first();

        if (! $layer || $layer->remaining_quantity < $params['quantity']) {
            throw new InsufficientCostLayersException(
                $layer?->product_id ?? 0,
                $params['quantity'],
                $layer?->remaining_quantity ?? 0,
            );
        }

        $consume = $params['quantity'];
        $layer->decrement('remaining_quantity', $consume);

        $consumption = CostLayerConsumption::query()->create([
            'cost_layer_id' => $layer->id,
            'inventory_adjustment_id' => $params['inventory_adjustment_id'] ?? null,
            'quantity_consumed' => $consume,
            'unit_cost' => $layer->unit_cost,
        ]);

        $totalCost = $consume * $layer->unit_cost;

        return [
            'consumptions' => [$consumption],
            'total_cost' => $totalCost,
            'weighted_average_cost' => $layer->unit_cost,
        ];
    }

    /**
     * Handle a 'set' adjustment — creates or consumes layers based on the delta.
     *
     * @return array{layer?: InventoryCostLayer, consumption_result?: array{consumptions: list<CostLayerConsumption>, total_cost: int, weighted_average_cost: int}}
     */
    public function handleSetAdjustment(
        Product $product,
        int $userId,
        int $stockBefore,
        int $stockAfter,
        ?int $unitCost,
        ?int $adjustmentId,
    ): array {
        $delta = $stockAfter - $stockBefore;

        if ($delta > 0) {
            $layer = $this->createLayer([
                'product_id' => $product->id,
                'user_id' => $userId,
                'quantity' => $delta,
                'unit_cost' => $unitCost ?? $product->cost ?? $product->price,
                'inventory_adjustment_id' => $adjustmentId,
                'reference' => $adjustmentId ? 'ADJ-'.$adjustmentId : null,
            ]);

            return ['layer' => $layer];
        }

        if ($delta < 0) {
            $this->ensureLayersExist($product, $userId);

            $result = $this->consumeLayers([
                'product_id' => $product->id,
                'quantity' => abs($delta),
                'inventory_adjustment_id' => $adjustmentId,
            ]);

            return ['consumption_result' => $result];
        }

        return [];
    }

    /**
     * Get the weighted average cost across all active cost layers for a product.
     */
    public function getWeightedAverageCost(int $productId): ?int
    {
        $layers = InventoryCostLayer::query()
            ->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->get();

        $totalQuantity = $layers->sum('remaining_quantity');

        if ($totalQuantity === 0) {
            return null;
        }

        $totalValue = $layers->sum(fn (InventoryCostLayer $layer) => $layer->remaining_quantity * $layer->unit_cost);

        return (int) round($totalValue / $totalQuantity);
    }

    /**
     * Backward compatibility — create a legacy layer if the product has stock but no active cost layers.
     */
    public function ensureLayersExist(Product $product, ?int $userId = null): void
    {
        $hasActiveLayers = InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->where('remaining_quantity', '>', 0)
            ->exists();

        if ($hasActiveLayers || $product->stock <= 0) {
            return;
        }

        $this->createLayer([
            'product_id' => $product->id,
            'user_id' => $userId ?? $product->user_id,
            'quantity' => $product->stock,
            'unit_cost' => $product->cost ?? $product->price,
            'reference' => 'MIGRATION',
        ]);
    }
}
