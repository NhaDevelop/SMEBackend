<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sectors = [
            [
                'name' => 'Agriculture & Agribusiness',
                'description' => 'Crop production, livestock, fishing, and related processing activities.',
                'color' => '#16a34a', // green-600
            ],
            [
                'name' => 'Manufacturing',
                'description' => 'Automotive, electronics, textiles, and other industrial production.',
                'color' => '#2563eb', // blue-600
            ],
            [
                'name' => 'Tourism & Hospitality',
                'description' => 'Hotels, travel agencies, tour operators, and recreational services.',
                'color' => '#ea580c', // orange-600
            ],
            [
                'name' => 'Digital & Technology',
                'description' => 'Software development, IT services, e-commerce, and digital innovation.',
                'color' => '#7c3aed', // violet-600
            ],
            [
                'name' => 'Food & Beverage',
                'description' => 'Food processing, restaurants, cafes, and catering services.',
                'color' => '#dc2626', // red-600
            ],
            [
                'name' => 'Healthcare & Wellness',
                'description' => 'Medical services, pharmaceuticals, and health-related products.',
                'color' => '#0891b2', // cyan-600
            ],
            [
                'name' => 'Logistics & Supply Chain',
                'description' => 'Transportation, warehousing, and distribution services.',
                'color' => '#4b5563', // gray-600
            ],
            [
                'name' => 'Retail & Commerce',
                'description' => 'Wholesale and retail trade, including brick-and-mortar and online shops.',
                'color' => '#db2777', // pink-600
            ],
        ];

        foreach ($sectors as $sector) {
            Sector::updateOrCreate(
                ['name' => $sector['name']],
                $sector
            );
        }
    }
}
