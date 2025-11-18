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
            
            // Tracking numbers
            $table->string('booking_number', 9)->unique()->nullable();
            $table->string('hwb_number', 4)->unique()->nullable();
            $table->string('van_number', 11)->unique()->nullable();
            
            // User association
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
                
            // Personal Information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('contact_number')->nullable();

            // Shipper Information
            $table->string('shipper_first_name');
            $table->string('shipper_last_name');
            $table->string('shipper_contact')->nullable();

            // Consignee Information
            $table->string('consignee_first_name');
            $table->string('consignee_last_name');
            $table->string('consignee_contact')->nullable();

            // Shipping Details
            $table->string('mode_of_service');
            $table->foreignId('container_size_id')->constrained('container_types');
            $table->integer('container_quantity')->default(1);
            $table->foreignId('origin_id')->constrained('ports');
            $table->foreignId('destination_id')->constrained('ports');
            $table->foreignId('shipping_line_id')->nullable()->constrained('shipping_lines');
            $table->foreignId('truck_comp_id')->nullable()->constrained('truck_comps');

            // Dates
$table->date('departure_date')->nullable();
$table->date('delivery_date')->nullable();

            // Terms
            $table->integer('terms')->default(0);

            // Location data
            $table->json('pickup_location')->nullable();
            $table->json('delivery_location')->nullable();

            // Status fields
            $table->enum('booking_status', ['pending', 'in_transit', 'delivered'])->default('pending');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_deleted')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['status', 'booking_status']);
            $table->index('email');
            $table->index('is_deleted');
            $table->index('user_id');
            $table->index('truck_comp_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
};