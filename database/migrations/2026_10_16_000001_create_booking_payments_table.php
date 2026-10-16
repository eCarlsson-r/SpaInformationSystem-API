<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_record_id')->nullable()->constrained('cart_records')->nullOnDelete();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->time('session_start');
            $table->time('session_end');
            $table->unsignedInteger('amount');
            $table->string('reference_id', 64)->unique();
            $table->string('gateway_session_id', 40)->nullable()->unique();
            $table->string('payment_link_url', 1000)->nullable();
            $table->string('gateway_payment_id', 64)->nullable();
            $table->string('status', 20)->default('creating');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('sales_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->timestamps();
            $table->index(['bed_id', 'session_date', 'status', 'expires_at']);
            $table->index(['employee_id', 'session_date', 'status', 'expires_at']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('sales_id')->nullable()->after('employee_id')->constrained('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_id');
        });
        Schema::dropIfExists('booking_payments');
    }
};
