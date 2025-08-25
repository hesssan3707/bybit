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
        Schema::create('spot_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_exchange_id');
            $table->string('order_id')->nullable(); // Exchange order ID
            $table->string('order_link_id')->nullable(); // Our internal reference
            $table->string('symbol'); // Trading pair (e.g., BTCUSDT)
            $table->string('base_coin'); // Base currency (e.g., BTC)
            $table->string('quote_coin'); // Quote currency (e.g., USDT)
            $table->enum('side', ['Buy', 'Sell']);
            $table->enum('order_type', ['Market', 'Limit']);
            $table->decimal('qty', 20, 8); // Quantity
            $table->decimal('price', 20, 8)->nullable(); // Price (null for market orders)
            $table->enum('time_in_force', ['GTC', 'IOC', 'FOK'])->default('GTC');
            $table->enum('status', ['New', 'PartiallyFilled', 'Filled', 'Cancelled', 'Rejected'])->default('New');
            $table->timestamp('order_created_at')->nullable();
            $table->json('raw_response')->nullable(); // Store complete API response
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('user_exchange_id')->references('id')->on('user_exchanges')->onDelete('cascade');
            
            // Indexes
            $table->index(['user_exchange_id', 'status']);
            $table->index('order_id');
            $table->index('symbol');
            $table->index('status');
            $table->index('order_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot_orders');
    }
};