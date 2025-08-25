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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('future_strict_mode')->default(false)->after('remember_token');
            $table->timestamp('future_strict_mode_activated_at')->nullable()->after('future_strict_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['future_strict_mode', 'future_strict_mode_activated_at']);
        });
    }
};
