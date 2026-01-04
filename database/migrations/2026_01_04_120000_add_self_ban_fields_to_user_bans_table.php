<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_bans', function (Blueprint $table) {
            $table->decimal('price_below', 20, 8)->nullable()->after('ban_type');
            $table->decimal('price_above', 20, 8)->nullable()->after('price_below');
            $table->boolean('lifted_by_price')->default(false)->after('price_above');
        });
    }

    public function down(): void
    {
        Schema::table('user_bans', function (Blueprint $table) {
            $table->dropColumn([
                'price_below',
                'price_above',
                'lifted_by_price',
            ]);
        });
    }
};

