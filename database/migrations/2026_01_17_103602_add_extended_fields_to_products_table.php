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
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('sku');
            $table->text('description')->nullable()->after('name');
            $table->unsignedInteger('cost')->nullable()->after('price');
            $table->string('unit', 20)->default('pc')->after('currency');
            $table->string('image')->nullable()->after('max_stock');
            $table->boolean('is_active')->default(true)->after('image');
            $table->softDeletes()->after('updated_at');

            $table->index('barcode');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['is_active']);
            $table->dropIndex(['barcode']);
            $table->dropColumn(['barcode', 'description', 'cost', 'unit', 'image', 'is_active']);
        });
    }
};
