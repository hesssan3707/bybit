<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spot_orders', function (Blueprint $table) {
            // Add filled_at column if it doesn't exist
            if (!Schema::hasColumn('spot_orders', 'filled_at')) {
                $table->timestamp('filled_at')->nullable()->after('order_created_at');
            }
            
            // Add cancelled_at column for consistency if it doesn't exist
            if (!Schema::hasColumn('spot_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('filled_at');
            }
            
            // Add order_updated_at column for consistency with orders table if it doesn't exist
            if (!Schema::hasColumn('spot_orders', 'order_updated_at')) {
                $table->timestamp('order_updated_at')->nullable()->after('cancelled_at');
            }
        });
        
        // Fix status enum to include 'cancelled' (lowercase) for consistency with orders table
        // First update any existing 'Cancelled' values to 'cancelled'
        DB::statement("UPDATE spot_orders SET status = 'cancelled' WHERE status = 'Cancelled'");
        
        // Then modify the enum to use lowercase 'cancelled'
        DB::statement("ALTER TABLE spot_orders MODIFY COLUMN status ENUM('New', 'PartiallyFilled', 'Filled', 'cancelled', 'Rejected') DEFAULT 'New'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spot_orders', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn(['filled_at', 'cancelled_at', 'order_updated_at']);
        });
        
        // Revert status enum to original values
        // First update any 'cancelled' values back to 'Cancelled'
        DB::statement("UPDATE spot_orders SET status = 'Cancelled' WHERE status = 'cancelled'");
        
        // Then revert the enum
        DB::statement("ALTER TABLE spot_orders MODIFY COLUMN status ENUM('New', 'PartiallyFilled', 'Filled', 'Cancelled', 'Rejected') DEFAULT 'New'");
    }
};