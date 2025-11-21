<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all tables in the database
        $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE != 'InnoDB'");

        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME;
            
            try {
                DB::statement("ALTER TABLE `{$tableName}` ENGINE=InnoDB");
                echo "Converted {$tableName} to InnoDB\n";
            } catch (\Exception $e) {
                echo "Failed to convert {$tableName}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse operation - once converted to InnoDB, we don't want to convert back
    }
};
