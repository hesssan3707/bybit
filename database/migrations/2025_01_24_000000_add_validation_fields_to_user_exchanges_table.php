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
            // API validation results
            $table->json('validation_results')->nullable()->after('admin_notes');
            $table->timestamp('last_validation_at')->nullable()->after('validation_results');
            $table->boolean('spot_access')->nullable()->after('last_validation_at');
            $table->boolean('futures_access')->nullable()->after('spot_access');
            $table->boolean('ip_access')->nullable()->after('futures_access');
            $table->text('validation_message')->nullable()->after('ip_access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_exchanges', function (Blueprint $table) {
            $table->dropColumn([
                'validation_results',
                'last_validation_at',
                'spot_access',
                'futures_access',
                'ip_access',
                'validation_message'
            ]);
        });
    }
};