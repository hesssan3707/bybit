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
            $table->unsignedBigInteger('user_id');
            $table->string('exchange_name'); // bybit, binance, bingx, etc.
            $table->text('api_key'); // Encrypted
            $table->text('api_secret'); // Encrypted
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected', 'deactivated'])->default('pending');
            
            // Activation workflow fields
            $table->timestamp('activation_requested_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('deactivated_by')->nullable();
            
            // Admin notes and user reason
            $table->text('user_reason')->nullable();
            $table->text('admin_notes')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('activated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deactivated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
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