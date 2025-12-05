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
        Schema::create('compensations', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('employee_id')->index('emp-code');
            $table->integer('period_id')->index('gaji-periode');
            $table->integer('base_salary');
            $table->integer('therapist_bonus');
            $table->integer('recruit_bonus');
            $table->integer('addition');
            $table->string('addition_description', 500)->default('');
            $table->integer('deduction');
            $table->string('deduction_description', 500)->default('');
            $table->integer('total');

            $table->foreign(['period_id'], 'compensations_ibfk_1')->references(['id'])->on('periods')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compensations');
    }
};
