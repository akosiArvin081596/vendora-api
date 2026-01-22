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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payment_number');
            $table->dateTime('paid_at');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('PHP');
            $table->string('method');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'payment_number']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'method']);
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
