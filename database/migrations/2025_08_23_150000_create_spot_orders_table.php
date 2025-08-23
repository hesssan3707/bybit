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
            $table->string('order_id')->nullable()->index(); // Bybit order ID
            $table->string('order_link_id')->nullable()->index(); // Our generated UUID
            $table->string('symbol'); // Trading pair (e.g., BTCUSDT)
            $table->string('base_coin'); // Base currency (e.g., BTC)
            $table->string('quote_coin'); // Quote currency (e.g., USDT)
            $table->enum('side', ['Buy', 'Sell']); // Order side
            $table->enum('order_type', ['Market', 'Limit']); // Order type
            $table->decimal('qty', 20, 10); // Order quantity
            $table->decimal('price', 20, 10)->nullable(); // Order price (null for market orders)
            $table->decimal('executed_qty', 20, 10)->default(0); // Executed quantity
            $table->decimal('executed_price', 20, 10)->nullable(); // Average executed price
            $table->string('time_in_force')->default('GTC'); // GTC, IOC, FOK
            $table->enum('status', ['New', 'PartiallyFilled', 'Filled', 'Cancelled', 'Rejected', 'PartiallyFilledCanceled'])->default('New');
            $table->text('reject_reason')->nullable(); // Rejection reason if any
            $table->decimal('commission', 20, 10)->default(0); // Trading fee
            $table->string('commission_asset')->nullable(); // Fee currency
            $table->timestamp('order_created_at')->nullable(); // Bybit order creation time
            $table->timestamp('order_updated_at')->nullable(); // Bybit order update time
            $table->json('raw_response')->nullable(); // Store full Bybit response for debugging
            $table->timestamps(); // Laravel timestamps
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