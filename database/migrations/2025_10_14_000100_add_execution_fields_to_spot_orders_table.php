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
        Schema::table('spot_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('spot_orders', 'executed_qty')) {
                $table->decimal('executed_qty', 20, 8)->nullable()->after('qty');
            }

            if (!Schema::hasColumn('spot_orders', 'executed_price')) {
                $table->decimal('executed_price', 20, 8)->nullable()->after('executed_qty');
            }

            if (!Schema::hasColumn('spot_orders', 'commission')) {
                $table->decimal('commission', 20, 8)->nullable()->after('executed_price');
            }

            if (!Schema::hasColumn('spot_orders', 'commission_asset')) {
                $table->string('commission_asset', 32)->nullable()->after('commission');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spot_orders', function (Blueprint $table) {
            if (Schema::hasColumn('spot_orders', 'commission_asset')) {
                $table->dropColumn('commission_asset');
            }
            if (Schema::hasColumn('spot_orders', 'commission')) {
                $table->dropColumn('commission');
            }
            if (Schema::hasColumn('spot_orders', 'executed_price')) {
                $table->dropColumn('executed_price');
            }
            if (Schema::hasColumn('spot_orders', 'executed_qty')) {
                $table->dropColumn('executed_qty');
            }
        });
    }
};