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
        Schema::table('bybit_orders', function (Blueprint $table) {
            $table->dropColumn(['pnl', 'closure_price', 'closing_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bybit_orders', function (Blueprint $table) {
            $table->decimal('pnl', 20, 10)->nullable();
            $table->decimal('closure_price', 20, 10)->nullable();
            $table->string('closing_order_id')->nullable();
        });
    }
};
