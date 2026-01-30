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
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->integer('id', true);
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 10);
            $table->integer('wallet_id');
            $table->integer('amount');
            $table->string('description', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
