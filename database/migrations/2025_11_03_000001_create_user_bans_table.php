<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_bans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_demo')->default(false);
            $table->unsignedBigInteger('trade_id')->nullable();
            $table->string('ban_type'); // single_loss, double_loss, manual_close
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->index(['user_id', 'is_demo', 'ends_at']);
            $table->index(['user_id', 'is_demo', 'ban_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bans');
    }
};