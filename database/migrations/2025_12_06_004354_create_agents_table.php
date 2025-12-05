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
        Schema::create('agents', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('address', 1000)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('email', 500)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('mobile', 100)->nullable();
            $table->tinyInteger('discount')->nullable();
            $table->tinyInteger('commission')->nullable();
            $table->integer('liability_account')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
