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
            $table->date('session_date')->nullable();
            $table->time('session_time')->nullable();
            $table->integer('employee_id')->nullable();
            $table->string('treatment_id', 10)->nullable();
            $table->string('room_id', 11)->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('voucher_normal_quantity')->nullable();
            $table->integer('voucher_purchase_quantity')->nullable();
            $table->integer('price');
            $table->timestamp('created_at')->useCurrent()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
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
