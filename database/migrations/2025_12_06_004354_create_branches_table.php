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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('address', 500);
            $table->string('city', 50);
            $table->string('country', 50);
            $table->string('phone', 50);
            $table->string('description', 1000)->nullable();
            $table->string('cash_account', 6)->nullable();
            $table->string('walkin_account', 6)->nullable();
            $table->string('voucher_purchase_account', 6)->nullable();
            $table->string('voucher_usage_account', 6)->nullable();
            $table->text('image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
