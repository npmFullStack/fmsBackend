<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_lines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('base_rate_per_km', 10, 2);
            $table->decimal('weight_rate_per_km', 10, 4);
            $table->decimal('min_charge', 10, 2)->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_lines');
    }
};