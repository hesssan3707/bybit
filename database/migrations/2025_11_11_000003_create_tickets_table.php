<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('open'); // open, closed
            $table->text('reply')->nullable();
            $table->unsignedBigInteger('replied_by')->nullable(); // admin user id
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('replied_by')->references('id')->on('users')->onDelete('set null');
        });

        DB::statement('ALTER TABLE tickets ADD UNIQUE tickets_user_id_title_description_unique (user_id, title, description(50))');
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};