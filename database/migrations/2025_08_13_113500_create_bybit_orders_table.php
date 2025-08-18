<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBybitOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bybit_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
            $table->string('symbol')->default('ETH/USDT');
            $table->decimal('amount', 20, 10)->default(0.01);
            $table->string('side')->default('buy');
            $table->decimal('entry_low', 20, 10)->nullable();
            $table->decimal('entry_high', 20, 10)->nullable();
            $table->decimal('entry_price', 20, 10);
            $table->decimal('tp', 20, 10);
            $table->decimal('sl', 20, 10);
            $table->integer('steps')->default(1);
            $table->integer('leverage')->default(1);
            $table->integer('expire_minutes')->default(15);
            $table->string('status')->default('pending'); // pending, filled, canceled, closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bybit_orders');
    }
}
