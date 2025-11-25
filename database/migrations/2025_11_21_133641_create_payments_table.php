<?php
// [file name]: 2025_11_21_133641_create_payments_table.php

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
            $table->string('payment_method')->default('gcash');
            $table->string('reference_number')->unique()->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->date('payment_date')->nullable();
            
            // Payment provider fields (for GCash, PayMongo, Bank Transfer)
            $table->string('provider_payment_id')->nullable(); // Provider's payment ID
            $table->string('provider_checkout_url')->nullable(); // Checkout URL for redirect
            $table->json('provider_response')->nullable(); // Full provider response
            
            // Customer information for payment
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            
            // Payment timeline
            $table->timestamp('checkout_created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Additional metadata
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('booking_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('reference_number');
            $table->index('payment_method');
            $table->index('provider_payment_id');
            $table->index('created_at');
            $table->index('payment_date');
            $table->index('paid_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};