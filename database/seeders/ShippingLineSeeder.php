<?php

namespace Database\Seeders;

use App\Models\ShippingLine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShippingLineSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shippingLines = [
            [
                'name' => '2GO Travel',
                'is_deleted' => false,
            ],
            [
                'name' => 'Asian Marine Transport',
                'is_deleted' => false,
            ],
            [
                'name' => 'Lite Shipping Corporation',
                'is_deleted' => false,
            ],
            [
                'name' => 'Cokaliong Shipping Lines',
                'is_deleted' => false,
            ],
            [
                'name' => 'Trans-Asia Shipping Lines',
                'is_deleted' => false,
            ],
            [
                'name' => 'Philippine Span Asia Carrier',
                'is_deleted' => false,
            ],
            [
                'name' => 'Negros Navigation',
                'is_deleted' => false,
            ],
            [
                'name' => 'Super Shuttle RORO',
                'is_deleted' => false,
            ],
            [
                'name' => 'Medallion Transport',
                'is_deleted' => false,
            ],
            [
                'name' => 'Starlite Ferries',
                'is_deleted' => false,
            ],
            [
                'name' => 'FastCat',
                'is_deleted' => false,
            ],
            [
                'name' => 'Ocean Jet',
                'is_deleted' => false,
            ],
            [
                'name' => 'Aleson Shipping Lines',
                'is_deleted' => false,
            ],
            [
                'name' => 'George & Peter Lines',
                'is_deleted' => false,
            ],
            [
                'name' => 'Sulpicio Lines',
                'is_deleted' => false,
            ],
        ];

        foreach ($shippingLines as $shippingLine) {
            ShippingLine::create($shippingLine);
        }
    }
}