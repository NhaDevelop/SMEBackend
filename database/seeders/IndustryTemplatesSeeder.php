<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\Question;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndustryTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'ADMIN')->first() ?? User::factory()->create(['role' => 'ADMIN']);

        // --- TEMPLATE 1: AGRITECH & SUSTAINABLE FARMING ---
        $agriTemplate = Template::updateOrCreate(
            ['name' => 'AgriTech & Sustainable Farming Readiness'],
            [
                'version' => '1.0',
                'industry' => 'AgriTech',
                'status' => 'Active',
            ]
        );

        $this->seedAgriQuestions($agriTemplate->id);

        Program::updateOrCreate(
            ['slug' => Str::slug('Sustainable Agriculture Accelerator')],
            [
                'name' => 'Sustainable Agriculture Accelerator',
                'description' => 'A program dedicated to SMEs in the AgriTech space, focusing on sustainable practices and supply chain optimization.',
                'template_id' => $agriTemplate->id,
                'status' => 'Published',
                'start_date' => now()->addDays(2),
                'end_date' => now()->addMonths(8),
                'enrollment_deadline' => now()->addDays(1),
                'sector' => 'AgriTech, BioTech, Logistics',
                'duration' => '8 months',
                'investment_amount' => 75000.00,
                'benefits' => [
                    'GHP/GMP Compliance Support',
                    'Traceability System Implementation',
                    'Export Readiness Workshops',
                    'Access to ESG Investors'
                ],
                'created_by_user_id' => $admin->id,
            ]
        );

        // --- TEMPLATE 2: TECH SAAS & DIGITAL INNOVATION ---
        $saasTemplate = Template::updateOrCreate(
            ['name' => 'Tech SaaS & Digital Scalability'],
            [
                'version' => '2.1',
                'industry' => 'Technology',
                'status' => 'Active',
            ]
        );

        $this->seedSaasQuestions($saasTemplate->id);

        Program::updateOrCreate(
            ['slug' => Str::slug('SaaS Global Expansion Program')],
            [
                'name' => 'SaaS Global Expansion Program',
                'description' => 'Scale your software business globally. This program targets SaaS companies with proven traction and high growth potential.',
                'template_id' => $saasTemplate->id,
                'status' => 'Published',
                'start_date' => now()->addDays(5),
                'end_date' => now()->addMonths(12),
                'enrollment_deadline' => now()->addDays(4),
                'sector' => 'Technology, SaaS, Cloud, AI',
                'duration' => '12 months',
                'investment_amount' => 150000.00,
                'benefits' => [
                    'International Patent Assistance',
                    'Subscription Model Optimization',
                    'Cloud Architecture Security Audit',
                    'Series A Pitch Preparation'
                ],
                'created_by_user_id' => $admin->id,
            ]
        );

        // --- TEMPLATE 3: GREEN ENERGY & ESG COMPLIANCE ---
        $greenTemplate = Template::updateOrCreate(
            ['name' => 'Green Energy & ESG Investment Readiness'],
            [
                'version' => '1.5',
                'industry' => 'Green Energy',
                'status' => 'Active',
            ]
        );

        $this->seedGreenQuestions($greenTemplate->id);

        Program::updateOrCreate(
            ['slug' => Str::slug('Clean Tech Innovation Fund')],
            [
                'name' => 'Clean Tech Innovation Fund',
                'description' => 'A funding-heavy program for companies building tomorrow\'s renewable energy solutions and circular economy models.',
                'template_id' => $greenTemplate->id,
                'status' => 'Published',
                'start_date' => now()->addMonth(),
                'end_date' => now()->addMonths(18),
                'enrollment_deadline' => now()->addDays(20),
                'sector' => 'Renewable Energy, Circular Economy, ESG',
                'duration' => '1.5 years',
                'investment_amount' => 250000.00,
                'benefits' => [
                    'Carbon Offset Validation',
                    'Sustainability Reporting Framework',
                    'Green Bond Training',
                    'Direct Path to Impact Investors'
                ],
                'created_by_user_id' => $admin->id,
            ]
        );
    }

    private function seedAgriQuestions($templateId)
    {
        $questions = [
            // Pillar 1: Team & Leadership
            ['pillar_id' => 1, 'text' => 'Does your management have expertise in Agri-Food Science?', 'type' => 'Yes/No', 'weight' => 5],
            // Pillar 2: Business Model
            ['pillar_id' => 2, 'text' => 'What is your primary farming model?', 'type' => 'Multiple Choice', 'weight' => 7, 'options' => [
                ['label' => 'Direct Farming', 'points' => 10], 
                ['label' => 'Contract Farming', 'points' => 8], 
                ['label' => 'Market Sourcing', 'points' => 3]
            ]],
            // Pillar 3: Market
            ['pillar_id' => 3, 'text' => 'Rate your current distribution reach Provincial vs National Stage.', 'type' => 'Scale (1-10)', 'weight' => 10],
            // Pillar 4: Financial
            ['pillar_id' => 4, 'text' => 'What was your annual revenue last fiscal year?', 'type' => 'Number', 'weight' => 15],
            // Pillar 5: Operations
            ['pillar_id' => 5, 'text' => 'Do you use IoT sensors for soil or water monitoring?', 'type' => 'Yes/No', 'weight' => 8],
            // Pillar 6: Legal
            ['pillar_id' => 6, 'text' => 'Is your land title properly registered for agricultural use?', 'type' => 'Yes/No', 'weight' => 15],
            // Pillar 7: Data
            ['pillar_id' => 7, 'text' => 'What CRM or ERP do you use to track crop cycles?', 'type' => 'Short Text', 'weight' => 5],
            // Pillar 8: Growth
            ['pillar_id' => 8, 'text' => 'How many hectares are under management?', 'type' => 'Number', 'weight' => 20]
        ];

        foreach ($questions as $q) {
            Question::updateOrCreate(
                ['text' => $q['text'], 'template_id' => $templateId],
                array_merge(['required' => true], $q)
            );
        }
    }

    private function seedSaasQuestions($templateId)
    {
        $questions = [
            ['pillar_id' => 1, 'text' => 'Do you have a CTO or lead engineer in the founding team?', 'type' => 'Yes/No', 'weight' => 10],
            ['pillar_id' => 2, 'text' => 'Rate your Monthly Recurring Revenue (MRR) stability.', 'type' => 'Scale (1-10)', 'weight' => 15],
            ['pillar_id' => 3, 'text' => 'What is your current Customer Acquisition Cost (CAC) in USD?', 'type' => 'Number', 'weight' => 10],
            ['pillar_id' => 4, 'text' => 'Is your software hosted on a reputable cloud provider (AWS/GCP/Azure)?', 'type' => 'Yes/No', 'weight' => 5],
            ['pillar_id' => 5, 'text' => 'How long does it take to onboard a new B2B client?', 'type' => 'Multiple Choice', 'weight' => 8, 'options' => [
                ['label' => '< 24 Hours', 'points' => 10], 
                ['label' => '1-3 days', 'points' => 7], 
                ['label' => '1 week+', 'points' => 2]
            ]],
            ['pillar_id' => 6, 'text' => 'Describe your current intellectual property status in short.', 'type' => 'Short Text', 'weight' => 12],
            ['pillar_id' => 7, 'text' => 'What percentage of your operations are fully digital/paperless?', 'type' => 'Scale (1-10)', 'weight' => 10],
            ['pillar_id' => 8, 'text' => 'Select your target market expansion priority.', 'type' => 'Multiple Choice', 'weight' => 20, 'options' => [
                ['label' => 'Southeast Asia', 'points' => 15], 
                ['label' => 'North America', 'points' => 20], 
                ['label' => 'Global', 'points' => 20]
            ]]
        ];

        foreach ($questions as $q) {
            Question::updateOrCreate(
                ['text' => $q['text'], 'template_id' => $templateId],
                array_merge(['required' => true], $q)
            );
        }
    }

    private function seedGreenQuestions($templateId)
    {
        $questions = [
            ['pillar_id' => 1, 'text' => 'Do you have an ESG compliance officer?', 'type' => 'Yes/No', 'weight' => 10],
            ['pillar_id' => 2, 'text' => 'Rate your business impact on local biodiversity.', 'type' => 'Scale (1-10)', 'weight' => 15],
            ['pillar_id' => 3, 'text' => 'What is your estimated annual carbon footprint (Tons of CO2)?', 'type' => 'Number', 'weight' => 10],
            ['pillar_id' => 4, 'text' => 'Has your company received any environmental certifications?', 'type' => 'Yes/No', 'weight' => 20],
            ['pillar_id' => 5, 'text' => 'What percentage of your power source is renewable?', 'type' => 'Multiple Choice', 'weight' => 15, 'options' => [
                ['label' => '100%', 'points' => 20], 
                ['label' => '50-99%', 'points' => 15], 
                ['label' => '10-49%', 'points' => 7], 
                ['label' => '0%', 'points' => 0]
            ]],
            ['pillar_id' => 6, 'text' => 'Are your board minutes publicly accessible?', 'type' => 'Yes/No', 'weight' => 10],
            ['pillar_id' => 7, 'text' => 'Do you use digital monitoring for waste/output efficiency?', 'type' => 'Yes/No', 'weight' => 10],
            ['pillar_id' => 8, 'text' => 'Describe your plan for scaling your environmental impact.', 'type' => 'Short Text', 'weight' => 10]
        ];

        foreach ($questions as $q) {
            Question::updateOrCreate(
                ['text' => $q['text'], 'template_id' => $templateId],
                array_merge(['required' => true], $q)
            );
        }
    }
}
