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
        Schema::table('user_account_settings', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('user_id');
            
            // Drop old unique constraint
            $table->dropUnique(['user_id', 'key']);
            
            // Add new unique constraint with is_demo
            $table->unique(['user_id', 'key', 'is_demo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_account_settings', function (Blueprint $table) {
            // Drop new unique constraint
            $table->dropUnique(['user_id', 'key', 'is_demo']);
            
            // Restore old unique constraint
            $table->unique(['user_id', 'key']);
            
            $table->dropColumn('is_demo');
        });
    }
};
