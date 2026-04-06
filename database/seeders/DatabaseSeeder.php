<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Pillar;
use App\Models\Template;
use App\Models\Question;
use App\Models\Program;
use App\Models\ProgramEnrollment;
use App\Models\Sector;
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
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'full_name' => 'System Admin',
                'password' => bcrypt('password'),
                'phone' => '+855 12 345 678',
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
                'is_verified' => true,
            ]
        );

        // 2. Create an ACTIVE SME User with full Profile
        $sme = User::updateOrCreate(
            ['email' => 'sme@example.com'],
            [
                'full_name' => 'John SME Owner',
                'password' => bcrypt('password'),
                'phone' => '+855 98 765 432',
                'role' => 'SME',
                'status' => 'ACTIVE',
                'is_verified' => true,
            ]
        );

        $sme->smeProfile()->updateOrCreate(
            ['user_id' => $sme->id],
            [
                'company_name' => 'Tech Solutions Cambodia',
                'industry' => 'Technology',
                'website_url' => 'https://example-tech.com',
                'address' => 'Phnom Penh, Cambodia',
                'stage' => 'Growth',
                'team_size' => '11-50',
            ]
        );

        // 3. Create an ACTIVE Investor User
        $investorUser = User::updateOrCreate(
            ['email' => 'investor@example.com'],
            [
                'full_name' => 'Jane Investor',
                'password' => bcrypt('password'),
                'phone' => '+855 11 222 333',
                'role' => 'INVESTOR',
                'status' => 'ACTIVE',
                'is_verified' => true,
            ]
        );

        $investorUser->investorProfile()->updateOrCreate(
            ['user_id' => $investorUser->id],
            [
                'organization_name' => 'Venture Capital Partners',
                'industry' => 'Venture Capital',
                'address' => 'Singapore',
                'investor_type' => 'Venture Capital',
            ]
        );

        // 4. Create an UNVERIFIED SME User
        $unverifiedSme = User::updateOrCreate(
            ['email' => 'unverified@example.com'],
            [
                'full_name' => 'Alice Unverified',
                'password' => bcrypt('password'),
                'phone' => '+855 77 888 999',
                'role' => 'SME',
                'status' => 'ACTIVE',
                'is_verified' => false,
            ]
        );

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
            Pillar::updateOrCreate(
                ['name' => $p['name']],
                ['weight' => $p['weight']]
            );
        }

        // 5. Create a Template
        $template = Template::updateOrCreate(
            ['name' => 'Standard SME Assessment 2026'],
            [
                'version' => '1.0',
                'industry' => 'General',
                'status' => 'Active',
            ]
        );

        // 6. Create questions per pillar with proper options
        $yesNoOptions = [
            ['label' => 'Yes', 'value' => true, 'points' => 10],
            ['label' => 'No', 'value' => false, 'points' => 0]
        ];

        $questions = [
            // Pillar 1: Team & Leadership
            ['pillar_id' => 1, 'text' => 'Does your company have a founder with 3+ years of industry experience?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 1, 'text' => 'Rate the effectiveness of your leadership team.', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            [
                'pillar_id' => 1,
                'text' => 'What is the size of your core leadership team?',
                'type' => 'SingleChoice',
                'weight' => 6.25,
                'options' => [
                    ['label' => '1-2 people', 'value' => '1-2', 'points' => 3],
                    ['label' => '3-5 people', 'value' => '3-5', 'points' => 7],
                    ['label' => '6-10 people', 'value' => '6-10', 'points' => 10],
                    ['label' => '10+ people', 'value' => '10+', 'points' => 10]
                ]
            ],

            // Pillar 2: Business Model
            ['pillar_id' => 2, 'text' => 'Is your business model documented and repeatable?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 2, 'text' => 'Rate the clarity of your revenue streams.', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            [
                'pillar_id' => 2,
                'text' => 'How many primary revenue streams does your business have?',
                'type' => 'Dropdown',
                'weight' => 6.25,
                'options' => [
                    ['label' => 'Single revenue stream', 'value' => '1', 'points' => 3],
                    ['label' => '2-3 revenue streams', 'value' => '2-3', 'points' => 7],
                    ['label' => '4+ revenue streams', 'value' => '4+', 'points' => 10]
                ]
            ],

            // Pillar 3: Market & Traction
            ['pillar_id' => 3, 'text' => 'Have you identified a clear target market and customer segment?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 3, 'text' => 'Rate your current market traction (paying customers, growth).', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            [
                'pillar_id' => 3,
                'text' => 'Which customer acquisition channels are you currently using?',
                'type' => 'MultipleChoice',
                'weight' => 6.25,
                'options' => [
                    ['label' => 'Digital Marketing (Social Media, SEO)', 'value' => 'digital', 'points' => 2],
                    ['label' => 'Direct Sales', 'value' => 'direct', 'points' => 2],
                    ['label' => 'Partnerships/Referrals', 'value' => 'partners', 'points' => 3],
                    ['label' => 'Traditional Advertising', 'value' => 'traditional', 'points' => 2],
                    ['label' => 'Content Marketing', 'value' => 'content', 'points' => 2]
                ]
            ],

            // Pillar 4: Financial Readiness
            ['pillar_id' => 4, 'text' => 'Does your company maintain up-to-date financial statements?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 4, 'text' => 'Rate your current revenue stability.', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            ['pillar_id' => 4, 'text' => 'Do you track profit margins per unit produced?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 4, 'text' => 'Are operational costs reviewed monthly?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],

            // Pillar 5: Operations
            ['pillar_id' => 5, 'text' => 'Do you have documented standard operating procedures (SOPs)?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 5, 'text' => 'Rate your current operational efficiency.', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            [
                'pillar_id' => 5,
                'text' => 'What is your current order fulfillment process?',
                'type' => 'Dropdown',
                'weight' => 6.25,
                'options' => [
                    ['label' => 'Manual/No formal process', 'value' => 'manual', 'points' => 2],
                    ['label' => 'Partially automated', 'value' => 'partial', 'points' => 6],
                    ['label' => 'Fully automated', 'value' => 'automated', 'points' => 10]
                ]
            ],

            // Pillar 6: Legal & Governance
            ['pillar_id' => 6, 'text' => 'Is your company legally registered with all required licenses?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 6, 'text' => 'Rate the strength of your corporate governance structure.', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            ['pillar_id' => 6, 'text' => 'Do you have formal contracts for key business relationships?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],

            // Pillar 7: Data & Digital Maturity
            ['pillar_id' => 7, 'text' => 'Does your company use digital tools to manage operations?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 7, 'text' => 'Rate your data collection and analysis capabilities.', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            [
                'pillar_id' => 7,
                'text' => 'Which digital tools does your company currently use?',
                'type' => 'MultipleChoice',
                'weight' => 6.25,
                'options' => [
                    ['label' => 'CRM (Customer Relationship Management)', 'value' => 'crm', 'points' => 2],
                    ['label' => 'ERP (Enterprise Resource Planning)', 'value' => 'erp', 'points' => 3],
                    ['label' => 'Accounting Software', 'value' => 'accounting', 'points' => 2],
                    ['label' => 'Project Management Tools', 'value' => 'project', 'points' => 2],
                    ['label' => 'Analytics/Data Tools', 'value' => 'analytics', 'points' => 2]
                ]
            ],

            // Pillar 8: Growth & Scalability
            ['pillar_id' => 8, 'text' => 'Do you have a documented 3-year growth plan?', 'type' => 'Yes/No', 'weight' => 6.25, 'options' => $yesNoOptions],
            ['pillar_id' => 8, 'text' => 'Rate your current ability to scale operations without proportional cost increase.', 'type' => 'Scale (1-10)', 'weight' => 6.25, 'options' => null],
            [
                'pillar_id' => 8,
                'text' => 'What is your primary strategy for scaling the business?',
                'type' => 'SingleChoice',
                'weight' => 6.25,
                'options' => [
                    ['label' => 'Geographic expansion', 'value' => 'geographic', 'points' => 8],
                    ['label' => 'New product lines', 'value' => 'products', 'points' => 8],
                    ['label' => 'Partnerships/Franchising', 'value' => 'partnerships', 'points' => 10],
                    ['label' => 'Digital transformation', 'value' => 'digital', 'points' => 9]
                ]
            ],
        ];

        foreach ($questions as $q) {
            Question::updateOrCreate(
                ['text' => $q['text'], 'template_id' => $template->id],
                $q
            );
        }

        // 7. Seed Sectors
        $this->call(SectorSeeder::class);

        // 8. Seed Industry Templates & Programs
        $this->call(IndustryTemplatesSeeder::class);

        // 9. UpdateOrCreate a Program using the template
        $program = Program::updateOrCreate(
            ['name' => 'Investment Accelerator 2026'],
            [
                'description' => 'A comprehensive program to help SMEs prepare for investment readiness through structured assessment and mentorship.',
                'template_id' => $template->id,
                'status' => 'Published',
                'start_date' => now()->addDays(7),
                'end_date' => now()->addMonths(6),
                'enrollment_deadline' => now()->addDays(6),
                'sector' => 'Technology, Healthcare, FinTech, AgriTech, E-commerce, Manufacturing',
                'duration' => '6 months',
                'investment_amount' => 50000.00,
                'benefits' => [
                    'Investment readiness assessment',
                    'Mentorship from industry experts',
                    'Access to investor network'
                ],
                'created_by_user_id' => $admin->id
            ]
        );

        // 10. Enroll the SME user in the program
        $smeProfile = $sme->smeProfile;
        ProgramEnrollment::updateOrCreate(
            ['sme_id' => $smeProfile->id, 'program_id' => $program->id],
            [
                'status' => 'Enrolled',
                'enrollment_date' => now(),
            ]
        );

        echo "Mock data seeded successfully!\n(Passwords are all 'password')\n";
        echo "SME user is enrolled in program: {$program->name}\n";
        echo "Template has " . Question::where('template_id', $template->id)->count() . " questions\n";
    }
}
