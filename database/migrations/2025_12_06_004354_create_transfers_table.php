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
        Schema::create('transfers', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('journal_reference', 50);
            $table->date('date');
            $table->string('from_wallet_id', 50);
            $table->string('to_wallet_id', 50);
            $table->integer('amount');
            $table->string('description', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
