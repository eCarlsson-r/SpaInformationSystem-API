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
        Schema::create('expense_items', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('expense_id')->index('link expense');
            $table->string('account_id', 11)->default('');
            $table->integer('amount');
            $table->string('description', 50);

            $table->foreign(['expense_id'], 'expense_items_ibfk_1')->references(['id'])->on('expenses')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
