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
        Schema::table('payments', function (Blueprint $table) {
            // Make order_id nullable for credit repayments (not tied to a single order)
            $table->dropForeign(['order_id']);
            $table->foreignId('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();

            $table->foreignId('customer_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');

            $table->dropForeign(['order_id']);
            $table->foreignId('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
