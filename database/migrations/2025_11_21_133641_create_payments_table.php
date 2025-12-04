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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Payment details
            $table->enum('payment_method', ['cod', 'gcash'])->default('cod');
            $table->string('gcash_receipt_image')->nullable(); // Path to uploaded receipt image
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 12, 2); // Allows for larger amounts
            
            // Payment status tracking
            $table->enum('status', ['pending', 'verified', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('admin_notes')->nullable();
            
            // Dates
            $table->dateTime('payment_date')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            
            // Indexes for performance
            $table->index('status');
            $table->index('payment_method');
            $table->index('booking_id');
            $table->index('user_id');
            $table->index('created_at');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};