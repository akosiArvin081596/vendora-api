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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 36);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint');
            $table->string('http_method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->mediumText('response');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['key', 'user_id'], 'idem_key_user_unique');
            $table->index(['user_id', 'created_at'], 'idem_user_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
