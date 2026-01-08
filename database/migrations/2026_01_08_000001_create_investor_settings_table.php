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
        Schema::create('investor_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('investment_limit', 16, 2)->nullable();
            $table->boolean('is_trading_enabled')->default(true);
            $table->integer('allocation_percentage')->default(100);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Migrate existing data if any (assuming column exists)
        if (Schema::hasColumn('users', 'investment_limit')) {
            $usersWithLimit = DB::table('users')->whereNotNull('investment_limit')->get();
            foreach ($usersWithLimit as $user) {
                DB::table('investor_settings')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'investment_limit' => $user->investment_limit,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
            
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('investment_limit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'investment_limit')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('investment_limit', 16, 2)->nullable()->after('parent_id');
            });

            // Restore data
            $settings = DB::table('investor_settings')->get();
            foreach ($settings as $setting) {
                DB::table('users')
                    ->where('id', $setting->user_id)
                    ->update(['investment_limit' => $setting->investment_limit]);
            }
        }

        Schema::dropIfExists('investor_settings');
    }
};
