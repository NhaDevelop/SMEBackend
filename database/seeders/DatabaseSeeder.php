<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Pillar;
use App\Models\Template;
use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create an ADMIN user
        User::create([
            'full_name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'phone' => '+855 12 345 678',
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'is_verified' => true,
        ]);

        // 2. Create an ACTIVE SME User with full Profile
        $sme = User::create([
            'full_name' => 'John SME Owner',
            'email' => 'sme@example.com',
            'password' => bcrypt('password'),
            'phone' => '+855 98 765 432',
            'role' => 'SME',
            'status' => 'ACTIVE',
            'is_verified' => true,
        ]);

        $sme->smeProfile()->create([
            'company_name' => 'Tech Solutions Cambodia',
            'registration_number' => 'REG-123456789',
            'industry' => 'Technology',
            'stage' => 'Growth',
            'years_in_business' => '3-5 Years',
            'team_size' => '11-50',
            'address' => 'Phnom Penh, Cambodia',
            'readiness_score' => 85,
            'risk_level' => 'Low Risk',
        ]);

        // 3. Create an ACTIVE INVESTOR User with full Profile
        $investor = User::create([
            'full_name' => 'Jane Investor',
            'email' => 'investor@example.com',
            'password' => bcrypt('password'),
            'phone' => '+855 77 111 222',
            'role' => 'INVESTOR',
            'status' => 'ACTIVE',
            'is_verified' => true,
        ]);

        $investor->investorProfile()->create([
            'organization_name' => 'Global Ventures Capital',
            'registration_number' => 'INV-987654321',
            'investor_type' => 'Venture Capital',
            'industry' => 'Technology, Real Estate',
            'years_in_business' => '10+ Years',
            'team_size' => '51-200',
            'address' => 'Singapore',
            'min_ticket_size' => 50000.00,
            'max_ticket_size' => 1000000.00,
        ]);

        // 4. Create Pillars (8 official pillars, 12.5% each = 100%)
        $pillars = [
            ['name' => 'Team & Leadership',       'weight' => 12.5],
            ['name' => 'Business Model',           'weight' => 12.5],
            ['name' => 'Market & Traction',        'weight' => 12.5],
            ['name' => 'Financial Readiness',      'weight' => 12.5],
            ['name' => 'Operations',               'weight' => 12.5],
            ['name' => 'Legal & Governance',       'weight' => 12.5],
            ['name' => 'Data & Digital Maturity',  'weight' => 12.5],
            ['name' => 'Growth & Scalability',     'weight' => 12.5],
        ];

        foreach ($pillars as $p) {
            Pillar::create($p);
        }

        // 5. Create a Template
        $template = Template::create([
            'name' => 'Standard SME Assessment 2026',
            'version' => '1.0',
            'industry' => 'General',
            'status' => 'Active',
        ]);

        // 6. Create 2 sample Questions per pillar (16 total)
        $questions = [
            // Pillar 1: Team & Leadership
            ['pillar_id' => 1, 'text' => 'Does your company have a founder with 3+ years of industry experience?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 1, 'text' => 'Rate the effectiveness of your leadership team (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
            // Pillar 2: Business Model
            ['pillar_id' => 2, 'text' => 'Is your business model documented and repeatable?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 2, 'text' => 'Rate the clarity of your revenue streams (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
            // Pillar 3: Market & Traction
            ['pillar_id' => 3, 'text' => 'Have you identified a clear target market and customer segment?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 3, 'text' => 'Rate your current market traction (paying customers, growth) (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
            // Pillar 4: Financial Readiness
            ['pillar_id' => 4, 'text' => 'Does your company maintain up-to-date financial statements?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 4, 'text' => 'Rate your current revenue stability (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
            // Pillar 5: Operations
            ['pillar_id' => 5, 'text' => 'Do you have documented standard operating procedures (SOPs)?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 5, 'text' => 'Rate your current operational efficiency (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
            // Pillar 6: Legal & Governance
            ['pillar_id' => 6, 'text' => 'Is your company legally registered with all required licenses?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 6, 'text' => 'Rate the strength of your corporate governance structure (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
            // Pillar 7: Data & Digital Maturity
            ['pillar_id' => 7, 'text' => 'Does your company use digital tools to manage operations (e.g., ERP, CRM)?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 7, 'text' => 'Rate your data collection and analysis capabilities (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
            // Pillar 8: Growth & Scalability
            ['pillar_id' => 8, 'text' => 'Do you have a documented 3-year growth plan?', 'type' => 'Yes/No', 'weight' => 6.25],
            ['pillar_id' => 8, 'text' => 'Rate your current ability to scale operations without proportional cost increase (1-10).', 'type' => 'Scale (1-10)', 'weight' => 6.25],
        ];

        foreach ($questions as $q) {
            Question::create(array_merge(['template_id' => $template->id], $q));
        }

        // 7. Seed Sectors
        $this->call(SectorSeeder::class);

        echo "Mock data seeded successfully!\n(Passwords are all 'password')";
    }
}
