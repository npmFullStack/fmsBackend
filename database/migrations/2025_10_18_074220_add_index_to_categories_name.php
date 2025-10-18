<?php
// database/migrations/2024_xx_xx_xxxxxx_add_indexes_to_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add indexes to optimize queries on categories table
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {

            $table->index('name', 'categories_name_index');
            
            $table->index('base_rate', 'categories_base_rate_index');
            

            $table->index(['name', 'base_rate'], 'categories_name_base_rate_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_name_index');
            $table->dropIndex('categories_base_rate_index');
            $table->dropIndex('categories_name_base_rate_index');
        });
    }
};

