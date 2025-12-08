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
        Schema::create('employees', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id')->default(0);
            $table->string('complete_name', 200);
            $table->string('name', 50);
            $table->string('status', 10);
            $table->string('identity_type', 20);
            $table->string('identity_number', 50);
            $table->string('place_of_birth', 100);
            $table->date('date_of_birth');
            $table->tinyInteger('certified')->default(0);
            $table->boolean('vaccine1')->default(false);
            $table->boolean('vaccine2')->default(false);
            $table->integer('recruiter')->nullable();
            $table->string('branch_id', 10);
            $table->integer('base_salary');
            $table->string('expertise', 1000);
            $table->string('gender', 1);
            $table->string('phone', 50);
            $table->string('address', 100);
            $table->string('mobile', 50);
            $table->string('email', 100);
            $table->integer('absent_deduction')->default(50000);
            $table->integer('meal_fee')->default(0);
            $table->integer('late_deduction')->default(20000);
            $table->string('bank_account', 20)->default('');
            $table->string('bank', 10)->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
