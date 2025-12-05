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
        Schema::create('sessions', function (Blueprint $table) {
            $table->integer('id');
            $table->time('order_time');
            $table->dateTime('reserved_time')->nullable();
            $table->string('bed_id', 30);
            $table->integer('customer_id');
            $table->string('payment', 100);
            $table->date('date');
            $table->time('start');
            $table->time('end');
            $table->string('status', 10);
            $table->string('treatment_id', 10);
            $table->integer('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
