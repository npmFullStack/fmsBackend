<?php

namespace Database\Seeders;

use App\Models\Port;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PortSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ports = [
            [
                'route_name' => 'MNL',
                'name' => 'Port of Manila',
                'address' => 'Port Area, Manila, 1012 Metro Manila',
                'latitude' => 14.583197,
                'longitude' => 120.966003,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'CEB',
                'name' => 'Port of Cebu',
                'address' => 'Cebu Baseport, Cebu City, 6000 Cebu',
                'latitude' => 10.311688,
                'longitude' => 123.891232,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'BCD',
                'name' => 'Port of Bacolod',
                'address' => 'Bredco Port, Bacolod City, 6100 Negros Occidental',
                'latitude' => 10.696869,
                'longitude' => 122.966003,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'GES',
                'name' => 'Port of General Santos',
                'address' => 'Makarrwhal Port, General Santos City, 9500 South Cotabato',
                'latitude' => 6.106344,
                'longitude' => 125.179115,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'DVO',
                'name' => 'Port of Davao',
                'address' => 'Sasa Wharf, Davao City, 8000 Davao del Sur',
                'latitude' => 7.073967,
                'longitude' => 125.623543,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'ILO',
                'name' => 'Port of Iloilo',
                'address' => 'Fort San Pedro Drive, Iloilo City Proper, Iloilo City, 5000 Iloilo',
                'latitude' => 10.696869,
                'longitude' => 122.566002,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'ZAM',
                'name' => 'Port of Zamboanga',
                'address' => 'Port of Zamboanga, Zamboanga City, 7000 Zamboanga del Sur',
                'latitude' => 6.921442,
                'longitude' => 122.079025,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'BTG',
                'name' => 'Port of Batangas',
                'address' => 'Batangas Port, Batangas City, 4200 Batangas',
                'latitude' => 13.756465,
                'longitude' => 121.058311,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'CDO',
                'name' => 'Port of Cagayan de Oro',
                'address' => 'Macabalan Wharf, Cagayan de Oro City, 9000 Misamis Oriental',
                'latitude' => 8.482218,
                'longitude' => 124.647163,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'TAC',
                'name' => 'Port of Tacloban',
                'address' => 'Tacloban Port, Tacloban City, 6500 Leyte',
                'latitude' => 11.243543,
                'longitude' => 125.004822,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'LGQ',
                'name' => 'Port of Legazpi',
                'address' => 'Legazpi Port, Legazpi City, 4500 Albay',
                'latitude' => 13.139467,
                'longitude' => 123.744003,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'SUG',
                'name' => 'Port of Surigao',
                'address' => 'Surigao City Port, Surigao City, 8400 Surigao del Norte',
                'latitude' => 9.783333,
                'longitude' => 125.483330,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'PPS',
                'name' => 'Port of Puerto Princesa',
                'address' => 'Puerto Princesa Port, Puerto Princesa City, 5300 Palawan',
                'latitude' => 9.739170,
                'longitude' => 118.735001,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'CRK',
                'name' => 'Port of Cagayan',
                'address' => 'Port of Cagayan, Aparri, 3515 Cagayan',
                'latitude' => 18.361944,
                'longitude' => 121.637222,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'DGT',
                'name' => 'Port of Dumaguete',
                'address' => 'Dumaguete Port, Dumaguete City, 6200 Negros Oriental',
                'latitude' => 9.306840,
                'longitude' => 123.305351,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'TAG',
                'name' => 'Port of Tagbilaran',
                'address' => 'Tagbilaran City Port, Tagbilaran City, 6300 Bohol',
                'latitude' => 9.647740,
                'longitude' => 123.855110,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'OZC',
                'name' => 'Port of Ozamiz',
                'address' => 'Ozamiz City Port, Ozamiz City, 7200 Misamis Occidental',
                'latitude' => 8.148170,
                'longitude' => 123.843330,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'DPL',
                'name' => 'Port of Dipolog',
                'address' => 'Dipolog City Port, Dipolog City, 7100 Zamboanga del Norte',
                'latitude' => 8.588290,
                'longitude' => 123.341110,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'PAG',
                'name' => 'Port of Pagadian',
                'address' => 'Pagadian City Port, Pagadian City, 7016 Zamboanga del Sur',
                'latitude' => 7.825800,
                'longitude' => 123.437220,
                'is_deleted' => false,
            ],
            [
                'route_name' => 'JAS',
                'name' => 'Port of Jasaan',
                'address' => 'Jasaan Port, Jasaan, Misamis Oriental',
                'latitude' => 8.654170,
                'longitude' => 124.755280,
                'is_deleted' => false,
            ],
        ];

        foreach ($ports as $port) {
            Port::create($port);
        }
    }
}