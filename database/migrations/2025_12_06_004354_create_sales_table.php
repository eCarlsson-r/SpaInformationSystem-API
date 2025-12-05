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
        Schema::create('sales', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('branch_id', 5);
            $table->integer('customer_id');
            $table->date('date');
            $table->time('time');
            $table->tinyInteger('discount')->default(0);
            $table->integer('total');
            $table->integer('income_id')->default(0);
            $table->integer('employee_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
