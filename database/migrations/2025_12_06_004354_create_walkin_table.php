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
            $table->integer('id', true);
            $table->string('treatment_id', 10);
            $table->integer('customer_id')->default(0);
            $table->integer('sales_id')->default(0);
            $table->integer('session_id')->default(0)->index('walkin-session');
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
