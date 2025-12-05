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
        Schema::create('journal_records', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('journal_id')->index('journal-id');
            $table->string('account_id', 100)->nullable();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
            $table->string('description', 500);

            $table->foreign(['journal_id'], 'journal_records_ibfk_1')->references(['id'])->on('journals')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_records');
    }
};
