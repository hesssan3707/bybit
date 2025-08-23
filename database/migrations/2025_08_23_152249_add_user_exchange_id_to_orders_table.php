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
            // Add user_exchange_id foreign key
            $table->foreignId('user_exchange_id')->after('id')->constrained('user_exchanges')->onDelete('cascade');
            
            // Drop user_id since we'll get user through user_exchange
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Restore user_id column
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            
            // Drop user_exchange_id
            $table->dropForeign(['user_exchange_id']);
            $table->dropColumn('user_exchange_id');
        });
    }
};
