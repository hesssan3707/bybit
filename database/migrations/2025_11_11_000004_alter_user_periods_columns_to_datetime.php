<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE `user_periods` MODIFY COLUMN `started_at` DATETIME NOT NULL');
        DB::statement('ALTER TABLE `user_periods` MODIFY COLUMN `ended_at` DATETIME NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `user_periods` MODIFY COLUMN `started_at` TIMESTAMP NOT NULL');
        DB::statement('ALTER TABLE `user_periods` MODIFY COLUMN `ended_at` TIMESTAMP NULL DEFAULT NULL');
    }
};