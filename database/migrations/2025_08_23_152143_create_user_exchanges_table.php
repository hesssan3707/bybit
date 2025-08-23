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
        Schema::create('user_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('exchange_name'); // 'bybit', 'binance', 'bingx'
            $table->text('api_key'); // Encrypted API key
            $table->text('api_secret'); // Encrypted API secret
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->timestamp('activation_requested_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->text('user_reason')->nullable(); // User's reason for activation request
            $table->timestamps();
            
            // Ensure one default exchange per user
            $table->unique(['user_id', 'exchange_name']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_default']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_exchanges');
    }
};
