<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_candle_data', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');

            $table->string('exchange', 32);
            $table->string('symbol', 64);

            $table->decimal('entry_price', 16, 8)->nullable();
            $table->decimal('exit_price', 16, 8)->nullable();

            $table->timestamp('entry_time')->nullable();
            $table->timestamp('exit_time')->nullable();

            $table->json('candles_m1')->nullable();
            $table->json('candles_m5')->nullable();
            $table->json('candles_m15')->nullable();
            $table->json('candles_h1')->nullable();
            $table->json('candles_h4')->nullable();

            $table->timestamps();

            $table->unique(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_candle_data');
    }
};