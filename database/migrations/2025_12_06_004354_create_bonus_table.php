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
        Schema::create('bonus', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('treatment_id', 10);
            $table->string('grade', 1);
            $table->integer('gross_bonus');
            $table->integer('trainer_deduction')->default(0);
            $table->integer('savings_deduction')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus');
    }
};
