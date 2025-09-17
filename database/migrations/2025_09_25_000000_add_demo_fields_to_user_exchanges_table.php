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
        Schema::table('user_exchanges', function (Blueprint $table) {
            $table->text('demo_api_key')->nullable()->after('api_secret'); // Encrypted demo API key
            $table->text('demo_api_secret')->nullable()->after('demo_api_key'); // Encrypted demo API secret
            $table->boolean('is_demo_active')->default(false)->after('demo_api_secret'); // Whether demo mode is currently active
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_exchanges', function (Blueprint $table) {
            $table->dropColumn(['demo_api_key', 'demo_api_secret', 'is_demo_active']);
        });
    }
};