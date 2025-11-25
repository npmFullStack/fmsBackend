<?php
// [file name]: 2025_01_14_000000_create_payments_table.php

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
            $table->enum('status', ['pending', 'processing', 'paid', 'failed', 'cancelled'])->default('pending');
            $table->date('payment_date')->nullable();
            
            // Paymongo specific fields
            $table->string('paymongo_payment_id')->nullable();
            $table->string('paymongo_checkout_url')->nullable();
            $table->json('paymongo_response')->nullable();
            
            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['booking_id', 'user_id']);
            $table->index('status');
            $table->index('reference_number');
            $table->index('paymongo_payment_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};