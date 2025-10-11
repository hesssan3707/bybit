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
        Schema::table('orders', function (Blueprint $table) {
            // Add filled_quantity if missing
            if (!Schema::hasColumn('orders', 'filled_quantity')) {
                $table->decimal('filled_quantity', 12, 8)->nullable()->after('amount');
            }

            // Add average_price if missing
            if (!Schema::hasColumn('orders', 'average_price')) {
                $table->decimal('average_price', 10, 4)->nullable()->after('filled_quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'average_price')) {
                $table->dropColumn('average_price');
            }
            if (Schema::hasColumn('orders', 'filled_quantity')) {
                $table->dropColumn('filled_quantity');
            }
        });
    }
};