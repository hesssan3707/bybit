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
        Schema::create('user_account_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('key', 50); // Setting key name
            $table->text('value')->nullable(); // Setting value (stored as text, can be converted to appropriate type)
            $table->string('type', 20)->default('string'); // Data type (string, integer, decimal, boolean, json)
            $table->timestamps();
            
            // Ensure unique key per user
            $table->unique(['user_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_account_settings');
    }
};
