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
            $table->foreignId('header_img')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('body_img1')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('body_img2')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('body_img3')->nullable()->constrained('files')->nullOnDelete();
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
