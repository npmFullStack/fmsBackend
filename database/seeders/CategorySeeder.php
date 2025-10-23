<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Fragile', 'base_rate' => 120, 'weight_multiplier' => 15, 'surcharge_percentage' => 10],
            ['name' => 'Perishable', 'base_rate' => 90, 'weight_multiplier' => 10, 'surcharge_percentage' => 8],
            ['name' => 'Hazardous', 'base_rate' => 150, 'weight_multiplier' => 18, 'surcharge_percentage' => 15],
            ['name' => 'Heavy Load', 'base_rate' => 180, 'weight_multiplier' => 20, 'surcharge_percentage' => 6],
            ['name' => 'Standard Goods', 'base_rate' => 60, 'weight_multiplier' => 8, 'surcharge_percentage' => 3],
            ['name' => 'Liquid', 'base_rate' => 100, 'weight_multiplier' => 12, 'surcharge_percentage' => 7],
            ['name' => 'Electronics', 'base_rate' => 80, 'weight_multiplier' => 14, 'surcharge_percentage' => 6],
            ['name' => 'Documents', 'base_rate' => 40, 'weight_multiplier' => 5, 'surcharge_percentage' => 2],
            ['name' => 'Machinery', 'base_rate' => 160, 'weight_multiplier' => 18, 'surcharge_percentage' => 5],
            ['name' => 'Automotive Parts', 'base_rate' => 100, 'weight_multiplier' => 15, 'surcharge_percentage' => 5],
            ['name' => 'Cold Storage', 'base_rate' => 130, 'weight_multiplier' => 14, 'surcharge_percentage' => 9],
            ['name' => 'Textiles', 'base_rate' => 70, 'weight_multiplier' => 9, 'surcharge_percentage' => 4],
            ['name' => 'Furniture', 'base_rate' => 110, 'weight_multiplier' => 16, 'surcharge_percentage' => 5],
            ['name' => 'Medical Supplies', 'base_rate' => 120, 'weight_multiplier' => 10, 'surcharge_percentage' => 7],
            ['name' => 'Construction Materials', 'base_rate' => 140, 'weight_multiplier' => 19, 'surcharge_percentage' => 6],
            ['name' => 'Household Items', 'base_rate' => 80, 'weight_multiplier' => 10, 'surcharge_percentage' => 3],
            ['name' => 'Agricultural Goods', 'base_rate' => 90, 'weight_multiplier' => 11, 'surcharge_percentage' => 5],
            ['name' => 'Retail Merchandise', 'base_rate' => 70, 'weight_multiplier' => 8, 'surcharge_percentage' => 4],
            ['name' => 'Plastic Goods', 'base_rate' => 60, 'weight_multiplier' => 9, 'surcharge_percentage' => 3],
            ['name' => 'Office Supplies', 'base_rate' => 50, 'weight_multiplier' => 7, 'surcharge_percentage' => 2],
        ];

        foreach ($categories as $data) {
            Category::create($data);
        }
    }
}