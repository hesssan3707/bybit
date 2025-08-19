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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('side');
            $table->string('order_type');
            $table->decimal('leverage', 5, 2);
            $table->decimal('qty', 20, 10);
            $table->decimal('avg_entry_price', 20, 10);
            $table->decimal('avg_exit_price', 20, 10);
            $table->decimal('pnl', 20, 10);
            $table->string('order_id')->unique();
            $table->timestamp('closed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
