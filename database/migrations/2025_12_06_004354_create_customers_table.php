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
        Schema::create('customers', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('gender', 1);
            $table->string('address', 500)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('place_of_birth', 50);
            $table->date('date_of_birth')->nullable();
            $table->string('mobile', 50);
            $table->string('email', 100);
            $table->string('referral_code', 10)->nullable();
            $table->foreignId('liability_account')->constrained('accounts')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
