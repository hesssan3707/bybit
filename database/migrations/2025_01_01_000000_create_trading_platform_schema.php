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
        // Create users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique(); // For backward compatibility
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            
            // User activation and management fields
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->string('activation_token')->nullable();
            
            // Password reset fields
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_expires_at')->nullable();
            
            // API token field for Sanctum
            $table->string('api_token')->nullable();
            
            // Current active exchange reference
            $table->unsignedBigInteger('current_exchange_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('email');
            $table->index('username');
            $table->index('activation_token');
            $table->index('password_reset_token');
            $table->index('api_token');
        });

        // Create user_exchanges table (multi-exchange support)
        Schema::create('user_exchanges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('exchange_name'); // bybit, binance, bingx, etc.
            $table->text('api_key'); // Encrypted
            $table->text('api_secret'); // Encrypted
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected', 'deactivated'])->default('pending');
            
            // Activation workflow fields
            $table->timestamp('activation_requested_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('deactivated_by')->nullable();
            
            // Admin notes and user reason
            $table->text('user_reason')->nullable();
            $table->text('admin_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes and constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('activated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deactivated_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['user_id', 'exchange_name']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_default']);
            $table->index('status');
        });

        // Add foreign key constraint to users table for current_exchange_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_exchange_id')->references('id')->on('user_exchanges')->onDelete('set null');
            $table->foreign('activated_by')->references('id')->on('users')->onDelete('set null');
        });

        // Create orders table (futures trading)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_exchange_id')->nullable();
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
            
            // Indexes and constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_exchange_id')->references('id')->on('user_exchanges')->onDelete('set null');
            $table->index(['user_id', 'status']);
            $table->index(['user_exchange_id', 'status']);
            $table->index('order_id');
            $table->index('symbol');
            $table->index('status');
        });

        // Create spot_orders table (spot trading)
        Schema::create('spot_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_exchange_id')->nullable();
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
            
            // Indexes and constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_exchange_id')->references('id')->on('user_exchanges')->onDelete('set null');
            $table->index(['user_id', 'status']);
            $table->index(['user_exchange_id', 'status']);
            $table->index('order_id');
            $table->index('symbol');
            $table->index('status');
            $table->index('order_created_at');
        });

        // Create trades table (PnL history)
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_exchange_id')->nullable();
            $table->string('symbol');
            $table->enum('side', ['Buy', 'Sell']);
            $table->string('order_type');
            $table->decimal('leverage', 5, 2);
            $table->decimal('qty', 20, 8);
            $table->decimal('avg_entry_price', 20, 8);
            $table->decimal('avg_exit_price', 20, 8);
            $table->decimal('pnl', 20, 8); // Profit and Loss
            $table->string('order_id'); // Exchange order ID
            $table->timestamp('closed_at');
            $table->timestamps();
            
            // Indexes and constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_exchange_id')->references('id')->on('user_exchanges')->onDelete('set null');
            $table->index(['user_id', 'closed_at']);
            $table->index(['user_exchange_id', 'closed_at']);
            $table->index('order_id');
            $table->index('symbol');
            $table->index('closed_at');
            $table->index('pnl');
        });

        // Create personal_access_tokens table for Laravel Sanctum
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('trades');
        Schema::dropIfExists('spot_orders');
        Schema::dropIfExists('orders');
        
        // Remove foreign key first
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_exchange_id']);
            $table->dropForeign(['activated_by']);
        });
        
        Schema::dropIfExists('user_exchanges');
        Schema::dropIfExists('users');
    }
};