<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('futures_funding_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('exchange');
            $table->string('symbol')->nullable();
            $table->decimal('funding_rate', 20, 10)->nullable();
            $table->decimal('open_interest', 32, 10)->nullable();
            $table->decimal('total_market_value', 32, 10)->nullable();
            $table->timestamp('metric_time')->nullable()->index();
            $table->timestamps();
            $table->index(['exchange', 'symbol', 'metric_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('futures_funding_snapshots');
    }
};

