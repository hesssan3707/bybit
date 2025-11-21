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
        Schema::create('company_exchange_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('exchange_name'); // bybit, binance, bingx
            $table->enum('account_type', ['live', 'demo']);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('assigned_user_exchange_id')->nullable();
            $table->foreign('assigned_user_exchange_id')->references('id')->on('user_exchanges')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->text('user_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_exchange_requests');
    }
};