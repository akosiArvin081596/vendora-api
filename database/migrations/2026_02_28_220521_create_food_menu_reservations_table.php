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
        Schema::create('food_menu_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->unsignedInteger('servings')->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->dateTime('reserved_at')->nullable();
            $table->timestamps();

            $table->index(['food_menu_item_id', 'status'], 'fmr_item_status');
            $table->index(['user_id', 'status'], 'fmr_user_status');
            $table->index('customer_id', 'fmr_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_menu_reservations');
    }
};
