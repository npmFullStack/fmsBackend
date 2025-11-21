<?php
// [file name]: 2025_01_15_000000_create_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Payment details
            $table->string('payment_method')->default('gcash'); // gcash, paymongo, etc.
            $table->string('reference_number')->unique()->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            
            // GCash specific fields
            $table->string('gcash_mobile_number')->nullable();
            $table->string('gcash_receipt')->nullable();
            $table->string('gcash_transaction_id')->nullable();
            
            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('booking_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('reference_number');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};