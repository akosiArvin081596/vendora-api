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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number');
            $table->date('ordered_at');
            $table->string('status')->default('pending');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('total');
            $table->string('currency', 3)->default('PHP');
            $table->timestamps();

            $table->unique(['user_id', 'order_number']);
            $table->index(['user_id', 'status']);
            $table->index('ordered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
