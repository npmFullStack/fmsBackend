<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'General Cargo',
                'is_deleted' => false,
            ],
            [
                'name' => 'Fragile',
                'is_deleted' => false,
            ],
            [
                'name' => 'Hazardous Materials',
                'is_deleted' => false,
            ],
            [
                'name' => 'Perishable Goods',
                'is_deleted' => false,
            ],
            [
                'name' => 'Electronics',
                'is_deleted' => false,
            ],
            [
                'name' => 'Machinery & Equipment',
                'is_deleted' => false,
            ],
            [
                'name' => 'Construction Materials',
                'is_deleted' => false,
            ],
            [
                'name' => 'Food & Beverages',
                'is_deleted' => false,
            ],
            [
                'name' => 'Chemicals',
                'is_deleted' => false,
            ],
            [
                'name' => 'Pharmaceuticals',
                'is_deleted' => false,
            ],
            [
                'name' => 'Automotive Parts',
                'is_deleted' => false,
            ],
            [
                'name' => 'Textiles & Apparel',
                'is_deleted' => false,
            ],
            [
                'name' => 'Furniture',
                'is_deleted' => false,
            ],
            [
                'name' => 'Agricultural Products',
                'is_deleted' => false,
            ],
            [
                'name' => 'Live Animals',
                'is_deleted' => false,
            ],
            [
                'name' => 'Temperature Controlled',
                'is_deleted' => false,
            ],
            [
                'name' => 'Oversized Cargo',
                'is_deleted' => false,
            ],
            [
                'name' => 'High Value Cargo',
                'is_deleted' => false,
            ],
            [
                'name' => 'Document & Parcel',
                'is_deleted' => false,
            ],
            [
                'name' => 'Personal Effects',
                'is_deleted' => false,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}