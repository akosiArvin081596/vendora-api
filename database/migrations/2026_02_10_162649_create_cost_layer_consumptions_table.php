<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cost_layer_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_layer_id')->constrained('inventory_cost_layers')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_consumed');
            $table->integer('unit_cost');
            $table->timestamps();

            $table->index('order_item_id');
            $table->index('cost_layer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_layer_consumptions');
    }
};
