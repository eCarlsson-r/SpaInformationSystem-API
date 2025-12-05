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
        Schema::create('cart_records', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id');
            $table->string('session_type', 7);
            $table->date('session_date');
            $table->time('session_time');
            $table->integer('employee_id');
            $table->string('treatment_id', 10);
            $table->string('room_id', 11);
            $table->integer('quantity')->default(0);
            $table->integer('voucher_normal_quantity')->default(0);
            $table->integer('voucher_buy_quantity')->default(0);
            $table->integer('price');
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_records');
    }
};
