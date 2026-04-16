<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('conflicting_booking_id');
            $table->string('conflict_type');
            $table->timestamp('detection_timestamp');
            $table->string('resolution_status')->default('pending');
            $table->string('resolution_action')->nullable();
            $table->timestamp('resolution_timestamp')->nullable();
            $table->json('alternative_slots');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflicts');
    }
};
