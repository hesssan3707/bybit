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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(false)->after('email_verified_at');
            $table->string('activation_token')->nullable()->after('is_active');
            $table->string('password_reset_token')->nullable()->after('activation_token');
            $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_token');
            $table->timestamp('activated_at')->nullable()->after('password_reset_expires_at');
            $table->foreignId('activated_by')->nullable()->constrained('users')->after('activated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['activated_by']);
            $table->dropColumn([
                'email_verified_at',
                'is_active',
                'activation_token',
                'password_reset_token',
                'password_reset_expires_at',
                'activated_at',
                'activated_by'
            ]);
        });
    }
};