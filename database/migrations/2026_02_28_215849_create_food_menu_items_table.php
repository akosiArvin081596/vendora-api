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
        Schema::create('food_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('image')->nullable();
            $table->unsignedInteger('total_servings')->default(0);
            $table->unsignedInteger('reserved_servings')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_available'], 'fmi_user_available');
            $table->index(['user_id', 'category'], 'fmi_user_category');
            $table->index('store_id', 'fmi_store');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_menu_items');
    }
};
