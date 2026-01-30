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
        Schema::create('discounts', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 50);
            $table->string('type', 10);
            $table->integer('percent');
            $table->integer('amount');
            $table->integer('quantity');
            $table->date('expiry_date');
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
