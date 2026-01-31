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
        Schema::create('walkin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sales_id')->constrained('sales')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walkin');
    }
};
