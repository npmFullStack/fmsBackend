<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Category;
use App\Services\PricingService;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Fragile
            ['name' => 'LED Monitor 24 inch', 'category' => 'Fragile', 'weight' => 4.5, 'base_price' => 8500],
            ['name' => 'Ceramic Vase', 'category' => 'Fragile', 'weight' => 2.0, 'base_price' => 1200],
            ['name' => 'Glass Chandelier', 'category' => 'Fragile', 'weight' => 6.5, 'base_price' => 15000],
            ['name' => 'Wall Mirror', 'category' => 'Fragile', 'weight' => 5.0, 'base_price' => 2800],
            ['name' => 'Table Lamp', 'category' => 'Fragile', 'weight' => 1.8, 'base_price' => 900],

            // Perishable
            ['name' => 'Fresh Fruit Box', 'category' => 'Perishable', 'weight' => 3.0, 'base_price' => 600],
            ['name' => 'Frozen Meat Pack', 'category' => 'Perishable', 'weight' => 4.5, 'base_price' => 1500],
            ['name' => 'Dairy Crate', 'category' => 'Perishable', 'weight' => 5.0, 'base_price' => 1000],
            ['name' => 'Vegetable Basket', 'category' => 'Perishable', 'weight' => 2.5, 'base_price' => 500],
            ['name' => 'Seafood Box', 'category' => 'Perishable', 'weight' => 7.0, 'base_price' => 2200],

            // Hazardous
            ['name' => 'Car Battery 12V', 'category' => 'Hazardous', 'weight' => 10.0, 'base_price' => 4800],
            ['name' => 'Industrial Solvent Drum', 'category' => 'Hazardous', 'weight' => 20.0, 'base_price' => 6000],
            ['name' => 'Paint Can Set', 'category' => 'Hazardous', 'weight' => 8.0, 'base_price' => 1500],
            ['name' => 'Aerosol Can Box', 'category' => 'Hazardous', 'weight' => 5.0, 'base_price' => 800],
            ['name' => 'Cleaning Acid Container', 'category' => 'Hazardous', 'weight' => 12.0, 'base_price' => 3000],

            // Heavy Load
            ['name' => 'Portable Generator', 'category' => 'Heavy Load', 'weight' => 45.0, 'base_price' => 36000],
            ['name' => 'Steel Cabinet', 'category' => 'Heavy Load', 'weight' => 50.0, 'base_price' => 18000],
            ['name' => 'Washing Machine', 'category' => 'Heavy Load', 'weight' => 55.0, 'base_price' => 25000],
            ['name' => 'Industrial Compressor', 'category' => 'Heavy Load', 'weight' => 70.0, 'base_price' => 40000],
            ['name' => 'Construction Crane Part', 'category' => 'Heavy Load', 'weight' => 80.0, 'base_price' => 50000],

            // Standard Goods
            ['name' => 'Box of Clothes', 'category' => 'Standard Goods', 'weight' => 6.0, 'base_price' => 2800],
            ['name' => 'Packaged Shoes', 'category' => 'Standard Goods', 'weight' => 2.0, 'base_price' => 1500],
            ['name' => 'Kitchen Utensil Set', 'category' => 'Standard Goods', 'weight' => 3.0, 'base_price' => 1200],
            ['name' => 'Plastic Containers', 'category' => 'Standard Goods', 'weight' => 2.5, 'base_price' => 600],
            ['name' => 'Office Chair', 'category' => 'Standard Goods', 'weight' => 8.0, 'base_price' => 4000],

            // Liquid
            ['name' => 'Engine Oil 4L', 'category' => 'Liquid', 'weight' => 4.0, 'base_price' => 1100],
            ['name' => 'Water Gallon', 'category' => 'Liquid', 'weight' => 19.0, 'base_price' => 250],
            ['name' => 'Cooking Oil 1L x12', 'category' => 'Liquid', 'weight' => 12.0, 'base_price' => 1800],
            ['name' => 'Beverage Crate', 'category' => 'Liquid', 'weight' => 15.0, 'base_price' => 900],
            ['name' => 'Lubricant Drum', 'category' => 'Liquid', 'weight' => 25.0, 'base_price' => 5000],

            // Electronics
            ['name' => 'Smartphone', 'category' => 'Electronics', 'weight' => 0.3, 'base_price' => 25000],
            ['name' => 'Laptop', 'category' => 'Electronics', 'weight' => 1.5, 'base_price' => 48000],
            ['name' => 'Bluetooth Speaker', 'category' => 'Electronics', 'weight' => 0.8, 'base_price' => 4000],
            ['name' => 'Smartwatch', 'category' => 'Electronics', 'weight' => 0.2, 'base_price' => 9000],
            ['name' => 'Gaming Console', 'category' => 'Electronics', 'weight' => 4.0, 'base_price' => 28000],

            // Documents
            ['name' => 'Document Folder', 'category' => 'Documents', 'weight' => 0.2, 'base_price' => 250],
            ['name' => 'Legal Paper Bundle', 'category' => 'Documents', 'weight' => 2.0, 'base_price' => 600],
            ['name' => 'Blueprint Tube', 'category' => 'Documents', 'weight' => 1.5, 'base_price' => 450],
            ['name' => 'Confidential File Box', 'category' => 'Documents', 'weight' => 3.0, 'base_price' => 1200],
            ['name' => 'Report Folder Set', 'category' => 'Documents', 'weight' => 0.8, 'base_price' => 350],

            // Machinery
            ['name' => 'Lathe Machine Part', 'category' => 'Machinery', 'weight' => 35.0, 'base_price' => 15000],
            ['name' => 'Hydraulic Pump', 'category' => 'Machinery', 'weight' => 28.0, 'base_price' => 18000],
            ['name' => 'Drill Press Head', 'category' => 'Machinery', 'weight' => 40.0, 'base_price' => 25000],
            ['name' => 'Motor Assembly', 'category' => 'Machinery', 'weight' => 32.0, 'base_price' => 16000],
            ['name' => 'Gearbox Unit', 'category' => 'Machinery', 'weight' => 45.0, 'base_price' => 20000],

            // Automotive Parts
            ['name' => 'Brake Disc Set', 'category' => 'Automotive Parts', 'weight' => 10.0, 'base_price' => 4500],
            ['name' => 'Car Bumper', 'category' => 'Automotive Parts', 'weight' => 12.0, 'base_price' => 7000],
            ['name' => 'Muffler Pipe', 'category' => 'Automotive Parts', 'weight' => 8.0, 'base_price' => 2500],
            ['name' => 'Engine Mount Kit', 'category' => 'Automotive Parts', 'weight' => 6.0, 'base_price' => 2200],
            ['name' => 'Radiator', 'category' => 'Automotive Parts', 'weight' => 9.0, 'base_price' => 4000],

            // Cold Storage
            ['name' => 'Ice Cream Tub', 'category' => 'Cold Storage', 'weight' => 3.0, 'base_price' => 900],
            ['name' => 'Frozen Fish Box', 'category' => 'Cold Storage', 'weight' => 8.0, 'base_price' => 2000],
            ['name' => 'Vaccines Case', 'category' => 'Cold Storage', 'weight' => 5.0, 'base_price' => 6000],
            ['name' => 'Meat Crate', 'category' => 'Cold Storage', 'weight' => 7.0, 'base_price' => 1500],
            ['name' => 'Frozen Vegetables Box', 'category' => 'Cold Storage', 'weight' => 4.0, 'base_price' => 1000],

            // Textiles
            ['name' => 'Fabric Roll', 'category' => 'Textiles', 'weight' => 12.0, 'base_price' => 2200],
            ['name' => 'Curtain Set', 'category' => 'Textiles', 'weight' => 5.0, 'base_price' => 1200],
            ['name' => 'Towel Bale', 'category' => 'Textiles', 'weight' => 8.0, 'base_price' => 1600],
            ['name' => 'Bedsheet Pack', 'category' => 'Textiles', 'weight' => 3.0, 'base_price' => 1000],
            ['name' => 'Carpet Roll', 'category' => 'Textiles', 'weight' => 15.0, 'base_price' => 3500],

            // Furniture
            ['name' => 'Wooden Table', 'category' => 'Furniture', 'weight' => 30.0, 'base_price' => 7000],
            ['name' => 'Sofa Set', 'category' => 'Furniture', 'weight' => 60.0, 'base_price' => 25000],
            ['name' => 'Bookshelf', 'category' => 'Furniture', 'weight' => 25.0, 'base_price' => 8000],
            ['name' => 'Bed Frame', 'category' => 'Furniture', 'weight' => 50.0, 'base_price' => 18000],
            ['name' => 'Dining Chair', 'category' => 'Furniture', 'weight' => 6.0, 'base_price' => 2000],
        ];

        foreach ($items as $data) {
            $category = Category::where('name', $data['category'])->first();
            if (!$category) continue;

            $pricing = PricingService::calculateItemPrice(
                $data['base_price'],
                $data['weight'],
                $category
            );

            Item::create([
                'name' => $data['name'],
                'category_id' => $category->id,
                'weight' => $data['weight'],
                'base_price' => $data['base_price'],
                'calculated_price' => $pricing['item_total_price'],        'is_deleted' => false,
            ]);
        }
    }
}