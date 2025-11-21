<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Migrate old ban type to the new standardized name
        DB::table('user_bans')
            ->where('ban_type', 'open_ban_exchange_manual_close_3d')
            ->update(['ban_type' => 'exchange_force_close']);
    }

    public function down(): void
    {
        // Revert to the old name if needed
        DB::table('user_bans')
            ->where('ban_type', 'exchange_force_close')
            ->update(['ban_type' => 'open_ban_exchange_manual_close_3d']);
    }
};