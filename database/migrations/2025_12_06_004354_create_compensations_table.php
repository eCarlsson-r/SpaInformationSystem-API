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
            $table->id();
            $table->foreignId('employee_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('period_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->integer('base_salary');
            $table->integer('therapist_bonus');
            $table->integer('recruit_bonus');
            $table->integer('addition');
            $table->string('addition_description', 500)->nullable();
            $table->integer('deduction');
            $table->string('deduction_description', 500)->nullable();
            $table->integer('total');
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
