<?php
// [file name]: 2025_11_21_095418_create_accounts_receivables_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            
            // Financial details
            $table->decimal('total_expenses', 12, 2)->default(0);
            $table->decimal('total_payment', 12, 2)->default(0);
            $table->decimal('collectible_amount', 12, 2)->default(0);
            $table->decimal('gross_income', 12, 2)->default(0);
            $table->decimal('net_revenue', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            
            // Charges breakdown (store the actual charges from SendTotalPayment)
            $table->json('charges')->nullable();
            
            // Invoice and aging
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('aging_days')->default(0);
            $table->enum('aging_bucket', ['current', '1-30', '31-60', '61-90', 'over_90'])->default('current');
            $table->boolean('is_overdue')->default(false);
            
            // Status
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_deleted')->default(false);
            
            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('booking_id');
            $table->index('is_paid');
            $table->index('is_overdue');
            $table->index('aging_bucket');
            $table->index('is_deleted');
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounts_receivables');
    }
};