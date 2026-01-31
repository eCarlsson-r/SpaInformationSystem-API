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
            $table->id();
            $table->string('journal_reference', 50);
            $table->date('date');
            $table->foreignId('from_wallet_id')->constrained('wallets')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('to_wallet_id')->constrained('wallets')->onUpdate('cascade')->onDelete('cascade');
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
