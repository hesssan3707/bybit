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
            $table->string('tp_order_id')->nullable()->after('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bybit_orders', function (Blueprint $table) {
            $table->dropColumn('tp_order_id');
        });
    }
};
