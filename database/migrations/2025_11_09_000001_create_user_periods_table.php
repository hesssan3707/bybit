<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_demo')->default(false);
            $table->string('name', 100);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // Precomputed metrics (JSON blobs)
            $table->json('metrics_all')->nullable();
            $table->json('metrics_buy')->nullable();
            $table->json('metrics_sell')->nullable();
            // Per-exchange metrics: { exchange_name: { all: {...}, buy: {...}, sell: {...} } }
            $table->json('exchange_metrics')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_demo']);
            $table->index(['user_id', 'is_demo', 'is_active']);
            $table->index(['user_id', 'is_demo', 'is_default']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_periods');
    }
};