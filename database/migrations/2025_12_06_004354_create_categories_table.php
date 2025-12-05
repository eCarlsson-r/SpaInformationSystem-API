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
        Schema::create('categories', function (Blueprint $table) {
            $table->string('id', 10)->unique('category-code');
            $table->string('name', 50);
            $table->string('description', 200);
            $table->string('i18n', 50)->nullable();
            $table->string('header_img', 500);
            $table->string('body_img1', 500);
            $table->string('body_img2', 500);
            $table->string('body_img3', 500);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
