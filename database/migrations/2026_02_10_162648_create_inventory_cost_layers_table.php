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
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->integer('remaining_quantity');
            $table->integer('unit_cost');
            $table->timestamp('acquired_at');
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'remaining_quantity', 'acquired_at'], 'cost_layers_fifo_index');
            $table->index(['product_id', 'user_id'], 'cost_layers_product_user_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
    }
};
