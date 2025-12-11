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
            $table->id();
            $table->time('order_time')->nullable();
            $table->dateTime('reserved_time')->nullable();
            $table->foreignId('bed_id')->constrained('beds')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('payment', 100);
            $table->date('date');
            $table->time('start')->nullable();
            $table->time('end')->nullable();
            $table->string('status', 10);
            $table->string('treatment_id', 10);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
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
