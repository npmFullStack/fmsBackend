<?php

namespace Database\Seeders;

use App\Models\ContainerType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContainerTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $containerTypes = [
            [
                'size' => '20FT',
                'max_weight' => 28200.00, // 28,200 kg (62,170 lbs) - actual 20ft max payload
                'is_deleted' => false,
            ],
            [
                'size' => '40FT',
                'max_weight' => 26700.00, // 26,700 kg (58,860 lbs) - actual 40ft max payload
                'is_deleted' => false,
            ],
            [
                'size' => 'LCL',
                'max_weight' => 15000.00, // 15,000 kg - typical LCL shipment weight limit
                'is_deleted' => false,
            ],
        ];

        foreach ($containerTypes as $containerType) {
            ContainerType::create($containerType);
        }
    }
}