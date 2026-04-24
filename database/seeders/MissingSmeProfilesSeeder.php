<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SmeProfile;
use Illuminate\Database\Seeder;

class MissingSmeProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            'Technology', 'Retail', 'E-commerce', 'Logistics', 'FinTech',
            'Agriculture', 'Manufacturing', 'Healthcare', 'Tourism & Hospitality',
            'Food & Beverage', 'Education', 'Retail & Commerce', 'Digital & Technology',
        ];

        $stages = ['Ideation', 'Early Stage', 'Growth', 'Expansion', 'Mature'];

        $locations = [
            'Phnom Penh, Cambodia',
            'Siem Reap, Cambodia',
            'Battambang, Cambodia',
            'Kampong Cham, Cambodia',
            'Sihanoukville, Cambodia',
        ];

        // Find all ACTIVE SME users who do NOT have a profile
        $missing = User::where('role', 'SME')
            ->where('status', 'ACTIVE')
            ->whereDoesntHave('smeProfile')
            ->get();

        $created = 0;
        foreach ($missing as $user) {
            $industry  = $industries[array_rand($industries)];
            $stage     = $stages[array_rand($stages)];
            $location  = $locations[array_rand($locations)];
            $nameSlug  = preg_replace('/[^a-z0-9]/i', '', strtolower($user->full_name));
            $company   = ucwords($nameSlug) . ' ' . $industry . ' Co.';

            SmeProfile::create([
                'user_id'         => $user->id,
                'company_name'    => $company,
                'industry'        => $industry,
                'stage'           => $stage,
                'years_in_business' => rand(1, 10),
                'team_size'       => rand(3, 50),
                'address'         => $location,
                'readiness_score' => 0,
                'risk_level'      => null,
            ]);

            $created++;
            $this->command->info("  Created profile for user {$user->id}: {$user->full_name} → {$company}");
        }

        $this->command->info("\n✅ Created {$created} missing SME profiles.");
    }
}
