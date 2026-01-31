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
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('description', 5000);
            $table->integer('price');
            $table->integer('duration');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate()->cascadeOnDelete();
            $table->json('room');
            $table->string('applicable_days', 100);
            $table->time('applicable_time_end');
            $table->time('applicable_time_start');
            $table->integer('minimum_quantity');
            $table->integer('voucher_normal_quantity')->nullable();
            $table->integer('voucher_purchase_quantity')->nullable();
            $table->text('body_img');
            $table->text('icon_img');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
