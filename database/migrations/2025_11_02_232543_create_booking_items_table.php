<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('weight', 10, 2);
            $table->integer('quantity');
            $table->string('category');
            $table->timestamps();
            
            $table->index('booking_id');
            $table->index('category');
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_items');
    }
};