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
            // Add filled_at column
            $table->timestamp('filled_at')->nullable()->after('closed_at');
            
            // Fix status enum to include 'cancelled' instead of 'canceled'
            // Note: We need to use raw SQL for enum modification in MySQL
        });
        
        // Update the status enum using raw SQL to include both 'canceled' and 'cancelled'
        // This ensures backward compatibility while fixing the issue
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'filled', 'canceled', 'cancelled', 'expired', 'closed') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove filled_at column
            $table->dropColumn('filled_at');
        });
        
        // Revert status enum to original values
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'filled', 'canceled', 'expired', 'closed') DEFAULT 'pending'");
    }
};