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
        Schema::create('sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('sales')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('treatment_id', 5);
            $table->integer('quantity');
            $table->integer('price');
            $table->integer('discount');
            $table->string('redeem_type', 10);
            $table->string('voucher_start', 10)->nullable();
            $table->string('voucher_end', 10)->nullable();
            $table->integer('total_price');
            $table->string('description', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_records');
    }
};
