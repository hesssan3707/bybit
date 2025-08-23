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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique(); // For backward compatibility
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            
            // User activation and management fields
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->string('activation_token')->nullable();
            
            // Password reset fields
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_expires_at')->nullable();
            
            // API token field for Sanctum
            $table->string('api_token')->nullable();
            
            // Current active exchange reference (will be constrained later)
            $table->unsignedBigInteger('current_exchange_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('email');
            $table->index('username');
            $table->index('activation_token');
            $table->index('password_reset_token');
            $table->index('api_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};