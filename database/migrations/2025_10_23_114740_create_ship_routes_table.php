<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('ship_routes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('origin_id')->constrained('ports')->onDelete('cascade');
        $table->foreignId('destination_id')->constrained('ports')->onDelete('cascade');
        $table->foreignId('shipping_line_id')->constrained('shipping_lines')->onDelete('cascade');
        $table->decimal('distance_km', 10, 2);
        $table->boolean('is_deleted')->default(false);
        $table->timestamps();

        // Unique constraint to prevent duplicate routes for same shipping line
        $table->unique(['origin_id', 'destination_id', 'shipping_line_id']);
    });
}

    public function down(): void
    {
        Schema::dropIfExists('ship_routes');
    }
};