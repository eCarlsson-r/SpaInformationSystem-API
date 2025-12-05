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
        Schema::create('income_items', function (Blueprint $table) {
            $table->integer('id', true);
            $table->foreignId('income_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->string('type', 10);
            $table->string('transaction', 500);
            $table->integer('amount');
            $table->string('description', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_items');
    }
};
