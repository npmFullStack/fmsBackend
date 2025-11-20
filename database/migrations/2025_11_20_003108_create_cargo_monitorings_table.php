<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cargo_monitorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            
            // Status fields with timestamps
            $table->timestamp('pending_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('origin_port_at')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('destination_port_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Current status
            $table->enum('current_status', [
                'Pending',
                'Picked Up', 
                'Origin Port',
                'In Transit',
                'Destination Port',
                'Out for Delivery',
                'Delivered'
            ])->default('Pending');
            
            $table->timestamps();
            
            // Indexes
            $table->index('booking_id');
            $table->index('current_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cargo_monitorings');
    }
};