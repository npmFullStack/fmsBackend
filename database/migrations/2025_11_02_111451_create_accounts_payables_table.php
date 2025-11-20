<?php
// [file name]: 2025_11_02_111451_create_accounts_payables_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            
            // Main AP details - REMOVED voucher_number from here
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->decimal('total_expenses', 12, 2)->default(0);
            
            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('is_paid');
            $table->index('is_deleted');
            $table->index('booking_id');
        });

        // AP Freight Charges Table
        Schema::create('ap_freight_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_id')->constrained('accounts_payables')->onDelete('cascade');
            $table->string('voucher_number', 15)->unique(); // Voucher number for each charge
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('check_date')->nullable();
            $table->string('voucher', 100)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index('ap_id');
            $table->index('is_paid');
            $table->index('voucher_number');
        });

        // AP Trucking Charges Table
        Schema::create('ap_trucking_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_id')->constrained('accounts_payables')->onDelete('cascade');
            $table->string('voucher_number', 15)->unique(); // Voucher number for each charge
            $table->enum('type', ['ORIGIN', 'DESTINATION']);
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('check_date')->nullable();
            $table->string('voucher', 100)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['ap_id', 'type']);
            $table->index('is_paid');
            $table->index('voucher_number');
        });

        // AP Port Charges Table
        Schema::create('ap_port_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_id')->constrained('accounts_payables')->onDelete('cascade');
            $table->string('voucher_number', 15)->unique(); // Voucher number for each charge
            $table->enum('charge_type', [
                'CRAINAGE', 
                'ARRASTRE_ORIGIN', 
                'ARRASTRE_DEST',
                'WHARFAGE_ORIGIN', 
                'WHARFAGE_DEST',
                'LABOR_ORIGIN', 
                'LABOR_DEST'
            ]);
            $table->string('payee', 255)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('check_date')->nullable();
            $table->string('voucher', 100)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['ap_id', 'charge_type']);
            $table->index('is_paid');
            $table->index('voucher_number');
        });

        // AP Miscellaneous Charges Table
        Schema::create('ap_misc_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_id')->constrained('accounts_payables')->onDelete('cascade');
            $table->string('voucher_number', 15)->unique(); // Voucher number for each charge
            $table->enum('charge_type', [
                'REBATES', 
                'STORAGE', 
                'FACILITATION', 
                'DENR'
            ]);
            $table->string('payee', 255)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('check_date')->nullable();
            $table->string('voucher', 100)->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->index(['ap_id', 'charge_type']);
            $table->index('is_paid');
            $table->index('voucher_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ap_misc_charges');
        Schema::dropIfExists('ap_port_charges');
        Schema::dropIfExists('ap_trucking_charges');
        Schema::dropIfExists('ap_freight_charges');
        Schema::dropIfExists('accounts_payables');
    }
};