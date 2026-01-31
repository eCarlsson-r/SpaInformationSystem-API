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
        Schema::create('income_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->string('type', 20);
            $table->foreignId('wallet_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->integer('amount');
            $table->text('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_payments');
    }
};
