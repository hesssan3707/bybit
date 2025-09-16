<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('confirmation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code', 5);
            $table->boolean('used')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('email');
            $table->index('code');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('confirmation_codes');
    }
};