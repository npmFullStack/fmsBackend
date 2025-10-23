<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('base_rate', 10, 2);
            $table->decimal('weight_multiplier', 10, 4);
            $table->decimal('surcharge_percentage', 5, 2)->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};