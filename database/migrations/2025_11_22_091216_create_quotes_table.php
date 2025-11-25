<?php
// 2025_11_02_111452_create_quotes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            
            // Customer Information
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('contact_number')->nullable();

            // Shipper Information
            $table->string('shipper_first_name')->nullable();
            $table->string('shipper_last_name')->nullable();
            $table->string('shipper_contact')->nullable();

            // Consignee Information
            $table->string('consignee_first_name')->nullable();
            $table->string('consignee_last_name')->nullable();
            $table->string('consignee_contact')->nullable();

            // Shipping Details
            $table->string('mode_of_service');
            $table->foreignId('container_size_id')->constrained('container_types');
            $table->integer('container_quantity')->default(1);
            $table->foreignId('origin_id')->constrained('ports');
            $table->foreignId('destination_id')->constrained('ports');
            $table->foreignId('shipping_line_id')->nullable()->constrained('shipping_lines');
            $table->foreignId('truck_comp_id')->nullable()->constrained('truck_comps');

            // Terms
            $table->integer('terms')->default(0);

            // Location data
            $table->json('pickup_location')->nullable();
            $table->json('delivery_location')->nullable();

            // Quote Details
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->json('charges')->nullable(); 
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'accepted', 'rejected'])->default('pending');
            $table->boolean('is_deleted')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['status', 'is_deleted']);
            $table->index('email');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotes');
    }
};