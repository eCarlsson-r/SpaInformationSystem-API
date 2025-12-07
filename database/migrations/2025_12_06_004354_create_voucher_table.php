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
        Schema::create('voucher', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('treatment_id', 10);
            $table->date('register_date');
            $table->time('register_time');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->integer('amount');
            $table->date('purchase_date');
            $table->foreignId('sales_id')->constrained('sales')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher');
    }
};
