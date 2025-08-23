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
            
            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_exchange_id')->references('id')->on('user_exchanges')->onDelete('set null');
            
            // Indexes
            $table->index(['user_id', 'closed_at']);
            $table->index(['user_exchange_id', 'closed_at']);
            $table->index('order_id');
            $table->index('symbol');
            $table->index('closed_at');
            $table->index('pnl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};