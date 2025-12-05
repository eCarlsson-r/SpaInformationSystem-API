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
            $table->integer('id', true);
            $table->integer('sales_id')->index('sales-id');
            $table->string('treatment_id', 5);
            $table->integer('quantity');
            $table->integer('price');
            $table->integer('discount');
            $table->string('redeem_type', 10);
            $table->string('voucher_start', 10)->default('');
            $table->string('voucher_end', 10)->default('');
            $table->integer('total_price');
            $table->string('description', 500);

            $table->foreign(['sales_id'], 'sales_records_ibfk_1')->references(['id'])->on('sales')->onUpdate('cascade')->onDelete('cascade');
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
