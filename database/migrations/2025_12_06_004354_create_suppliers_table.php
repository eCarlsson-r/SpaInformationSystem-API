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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->string('contact', 500);
            $table->string('bank', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('address', 2000)->nullable();
            $table->string('mobile', 100)->nullable();
            $table->string('email', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
