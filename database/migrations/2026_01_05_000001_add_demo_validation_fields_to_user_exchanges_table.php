<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_exchanges', function (Blueprint $table) {
            $table->json('demo_validation_results')->nullable()->after('validation_message');
            $table->timestamp('demo_last_validation_at')->nullable()->after('demo_validation_results');
            $table->boolean('demo_spot_access')->nullable()->after('demo_last_validation_at');
            $table->boolean('demo_futures_access')->nullable()->after('demo_spot_access');
            $table->boolean('demo_ip_access')->nullable()->after('demo_futures_access');
            $table->text('demo_validation_message')->nullable()->after('demo_ip_access');
        });

        DB::table('user_exchanges')
            ->whereNotNull('demo_api_key')
            ->whereNotNull('demo_api_secret')
            ->update([
                'demo_validation_results' => DB::raw('validation_results'),
                'demo_last_validation_at' => DB::raw('last_validation_at'),
                'demo_spot_access' => DB::raw('spot_access'),
                'demo_futures_access' => DB::raw('futures_access'),
                'demo_ip_access' => DB::raw('ip_access'),
                'demo_validation_message' => DB::raw('validation_message'),
            ]);
    }

    public function down(): void
    {
        Schema::table('user_exchanges', function (Blueprint $table) {
            $table->dropColumn([
                'demo_validation_results',
                'demo_last_validation_at',
                'demo_spot_access',
                'demo_futures_access',
                'demo_ip_access',
                'demo_validation_message',
            ]);
        });
    }
};
