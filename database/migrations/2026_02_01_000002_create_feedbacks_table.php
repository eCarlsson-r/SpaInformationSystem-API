<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('comment');
            $table->decimal('sentiment_score', 4, 3)->nullable();
            $table->string('sentiment_label')->nullable();
            $table->string('analysis_status')->default('pending');
            $table->smallInteger('analysis_attempts')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamp('analyzed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
