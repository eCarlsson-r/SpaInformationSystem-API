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
        Schema::create('banners', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('image', 100);
            $table->string('introduction', 100);
            $table->string('title', 100);
            $table->string('subtitle', 100);
            $table->string('description', 100);
            $table->string('action', 100);
            $table->string('action_page', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
