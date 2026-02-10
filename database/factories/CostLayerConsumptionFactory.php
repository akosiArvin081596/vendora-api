<?php

namespace Database\Factories;

use App\Models\InventoryCostLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CostLayerConsumption>
 */
class CostLayerConsumptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cost_layer_id' => InventoryCostLayer::factory(),
            'order_item_id' => null,
            'inventory_adjustment_id' => null,
            'quantity_consumed' => $this->faker->numberBetween(1, 10),
            'unit_cost' => $this->faker->numberBetween(500, 50000),
        ];
    }
}
