<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            
            // Personal Information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('contact_number');
            
            // Shipper Information
            $table->string('shipper_first_name');
            $table->string('shipper_last_name');
            $table->string('shipper_contact');
            
            // Consignee Information
            $table->string('consignee_first_name');
            $table->string('consignee_last_name');
            $table->string('consignee_contact');
            
            // Shipping Preferences
            $table->string('mode_of_service');
            $table->string('container_size');
            $table->string('origin');
            $table->string('destination');
            $table->string('shipping_line')->nullable();
            $table->date('departure_date');
            $table->date('delivery_date')->nullable();
            
            // Location data (JSON for flexibility)
            $table->json('pickup_location')->nullable();
            $table->json('delivery_location')->nullable();
            
            // Items data (JSON array)
            $table->json('items');
            
            // Status and soft delete
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_deleted')->default(false);
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};