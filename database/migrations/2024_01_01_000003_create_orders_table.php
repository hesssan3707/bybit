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
            $table->unsignedBigInteger('user_exchange_id');
            $table->string('order_id')->nullable(); // Exchange order ID
            $table->string('order_link_id')->nullable(); // Our internal reference
            $table->string('symbol', 20)->default('ETHUSDT');
            $table->decimal('entry_price', 10, 4);
            $table->decimal('tp', 10, 4); // Take Profit
            $table->decimal('sl', 10, 4); // Stop Loss
            $table->integer('steps')->default(1);
            $table->integer('expire_minutes')->default(15);
            $table->enum('status', ['pending', 'filled', 'canceled', 'expired', 'closed'])->default('pending');
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('amount', 12, 8);
            $table->decimal('entry_low', 10, 4)->nullable();
            $table->decimal('entry_high', 10, 4)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('user_exchange_id')->references('id')->on('user_exchanges')->onDelete('cascade');
            
            // Indexes
            $table->index(['user_exchange_id', 'status']);
            $table->index('order_id');
            $table->index('symbol');
            $table->index('status');
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