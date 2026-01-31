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
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->integer('employee_id');
            $table->date('date');
            $table->string('shift_id', 3)->default('');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();

            $table->unique(['employee_id', 'date'], 'empdate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
