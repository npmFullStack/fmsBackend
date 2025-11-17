<?php

namespace Database\Seeders;

use App\Models\TruckComp;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TruckingCompanySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $truckingCompanies = [
            [
                'name' => 'Manila Trucking Services',
                'is_deleted' => false,
            ],
            [
                'name' => 'Royal Cargo Logistics',
                'is_deleted' => false,
            ],
            [
                'name' => 'Fast Logistics',
                'is_deleted' => false,
            ],
            [
                'name' => 'Air21',
                'is_deleted' => false,
            ],
            [
                'name' => '2GO Logistics',
                'is_deleted' => false,
            ],
            [
                'name' => 'LBC Express',
                'is_deleted' => false,
            ],
            [
                'name' => 'JRS Trucking',
                'is_deleted' => false,
            ],
            [
                'name' => 'Cargo Padala',
                'is_deleted' => false,
            ],
            [
                'name' => 'Rohlig Logistics',
                'is_deleted' => false,
            ],
            [
                'name' => 'DHL Supply Chain',
                'is_deleted' => false,
            ],
            [
                'name' => 'FedEx Logistics',
                'is_deleted' => false,
            ],
            [
                'name' => 'UPS Trucking',
                'is_deleted' => false,
            ],
            [
                'name' => 'PhilCargo',
                'is_deleted' => false,
            ],
            [
                'name' => 'Island Transport',
                'is_deleted' => false,
            ],
            [
                'name' => 'Mindanao Trucking Corp',
                'is_deleted' => false,
            ],
            [
                'name' => 'Visayas Cargo Movers',
                'is_deleted' => false,
            ],
            [
                'name' => 'Metro Manila Truckers',
                'is_deleted' => false,
            ],
            [
                'name' => 'Northern Luzon Haulers',
                'is_deleted' => false,
            ],
            [
                'name' => 'Southern Tagalog Transport',
                'is_deleted' => false,
            ],
            [
                'name' => 'Bicol Express Trucking',
                'is_deleted' => false,
            ],
        ];

        foreach ($truckingCompanies as $truckingCompany) {
            TruckComp::create($truckingCompany);
        }
    }
}