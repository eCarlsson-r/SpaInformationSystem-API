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
        Schema::create('wallets', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_id', 20)->nullable();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('edc_machine')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
