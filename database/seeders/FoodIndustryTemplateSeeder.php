<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;

class FoodIndustryTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templateId = 2; // "illegal Food" template

        // Clear existing questions for this template to avoid duplicates
        Question::where('template_id', $templateId)->delete();

        $questions = [
            // Pillar 1: Team & Leadership
            [
                'pillar_id' => 1,
                'text' => 'Does your management team include members with formal qualifications in Food Science or Nutrition?',
                'type' => 'Multiple Choice',
                'weight' => 5,
                'required' => true,
                'options' => [
                    ['label' => '3+ members', 'value' => '3+ members', 'points' => 5],
                    ['label' => '1-2 members', 'value' => '1-2 members', 'points' => 3],
                    ['label' => 'None', 'value' => 'None', 'points' => 0],
                ],
                'helper_text' => 'Technical education in the leadership team reduces operational risks.'
            ],
            [
                'pillar_id' => 1,
                'text' => 'Have your staff undergone mandatory Hygiene and Sanitization training in the last 6 months?',
                'type' => 'Yes/No',
                'weight' => 5,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 5],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Compliance with basic hygiene training is a legal requirement.'
            ],
            [
                'pillar_id' => 1,
                'text' => 'Is there a designated Quality Assurance (QA) officer in your facility?',
                'type' => 'Yes/No',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 10],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'A dedicated QA person is essential for consistent food quality.'
            ],

            // Pillar 2: Business Model
            [
                'pillar_id' => 2,
                'text' => 'Do you own or control your primary raw material sourcing (e.g., contract farming)?',
                'type' => 'Yes/No',
                'weight' => 8,
                'required' => true,
                'options' => [
                    ['label' => 'Yes (Direct Control)', 'value' => 'Yes (Direct Control)', 'points' => 8],
                    ['label' => 'No (Market Sourcing)', 'value' => 'No (Market Sourcing)', 'points' => 2],
                ],
                'helper_text' => 'Vertical integration provides better margin and quality control.'
            ],
            [
                'pillar_id' => 2,
                'text' => 'What is your primary revenue stream?',
                'type' => 'Multiple Choice',
                'weight' => 7,
                'required' => true,
                'options' => [
                    ['label' => 'B2B Retail Contracts', 'value' => 'B2B Retail Contracts', 'points' => 7],
                    ['label' => 'Direct to Consumer (D2C)', 'value' => 'Direct to Consumer (D2C)', 'points' => 5],
                    ['label' => 'Exports only', 'value' => 'Exports only', 'points' => 4],
                ],
                'helper_text' => 'Diversified revenue streams are preferred by investors.'
            ],
            [
                'pillar_id' => 2,
                'text' => 'Do you have a unique proprietary recipe or process that is protected (IP)?',
                'type' => 'Yes/No',
                'weight' => 10,
                'required' => false,
                'options' => [
                    ['label' => 'Yes (Trade Secret/Patent)', 'value' => 'Yes (Trade Secret/Patent)', 'points' => 10],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'IP protection creates a massive competitive moat.'
            ],

            // Pillar 3: Market & Traction
            [
                'pillar_id' => 3,
                'text' => 'How many active distribution outlets (stores/points of sale) sell your products?',
                'type' => 'Multiple Choice',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'More than 50', 'value' => 'More than 50', 'points' => 10],
                    ['label' => '10 to 50', 'value' => '10 to 50', 'points' => 5],
                    ['label' => 'Less than 10', 'value' => 'Less than 10', 'points' => 2],
                ],
                'helper_text' => 'Market footprint is a direct indicator of demand.'
            ],
            [
                'pillar_id' => 3,
                'text' => 'Have you achieved a customer retention rate of at least 30% month-over-month?',
                'type' => 'Yes/No',
                'weight' => 8,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 8],
                    ['label' => 'No', 'value' => 'No', 'points' => 2],
                ],
                'helper_text' => 'Loyal customers indicate product-market fit.'
            ],
            [
                'pillar_id' => 3,
                'text' => 'Do you have marketing budget and a dedicated social media presence?',
                'type' => 'Multiple Choice',
                'weight' => 7,
                'required' => false,
                'options' => [
                    ['label' => 'Active (>10k followers)', 'value' => 'Active (>10k followers)', 'points' => 7],
                    ['label' => 'Present (<10k followers)', 'value' => 'Present (<10k followers)', 'points' => 4],
                    ['label' => 'Minimal / None', 'value' => 'Minimal / None', 'points' => 0],
                ],
                'helper_text' => 'Branding helps build premium value.'
            ],

            // Pillar 4: Financial Readiness
            [
                'pillar_id' => 4,
                'text' => 'What is your current monthly revenue (average)?',
                'type' => 'Multiple Choice',
                'weight' => 15,
                'required' => true,
                'options' => [
                    ['label' => '>$50,000', 'value' => '>$50,000', 'points' => 15],
                    ['label' => '$10,000 - $50,000', 'value' => '$10,000 - $50,000', 'points' => 10],
                    ['label' => '<$10,000', 'value' => '<$10,000', 'points' => 5],
                ],
                'helper_text' => 'Scale of operations is critical for larger investments.'
            ],
            [
                'pillar_id' => 4,
                'text' => 'Are your financial statements audited by an external third party?',
                'type' => 'Yes/No',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 10],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Audit reports build investor trust significantly.'
            ],
            [
                'pillar_id' => 4,
                'text' => 'Do you have a clear financial projection for the next 3-5 years?',
                'type' => 'Yes/No',
                'weight' => 5,
                'required' => false,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 5],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Roadmaps show business maturity.'
            ],

            // Pillar 5: Operations
            [
                'pillar_id' => 5,
                'text' => 'What is the level of automation in your production line?',
                'type' => 'Multiple Choice',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'Fully Automated / Smart', 'value' => 'Fully Automated / Smart', 'points' => 10],
                    ['label' => 'Semi-Automated', 'value' => 'Semi-Automated', 'points' => 6],
                    ['label' => 'Largely Manual', 'value' => 'Largely Manual', 'points' => 2],
                ],
                'helper_text' => 'Automation reduces human error and increases scalability.'
            ],
            [
                'pillar_id' => 5,
                'text' => 'Do you have a formal Supplier Code of Conduct and periodic audits?',
                'type' => 'Yes/No',
                'weight' => 5,
                'required' => false,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 5],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Supply chain governance ensures quality from the source.'
            ],
            [
                'pillar_id' => 5,
                'text' => 'Is there a traceability system that tracks ingredients to specific batches?',
                'type' => 'Yes/No',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 10],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Batch traceability is mandatory for product recall readiness.'
            ],

            // Pillar 6: Legal & Governance
            [
                'pillar_id' => 6,
                'text' => 'Does your company hold valid export licenses for targeted foreign markets?',
                'type' => 'Yes/No',
                'weight' => 15,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 15],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Legal right to export is the first step in scaling globally.'
            ],
            [
                'pillar_id' => 6,
                'text' => 'Are your trademark and logo registered with the national IP office?',
                'type' => 'Yes/No',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 10],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'IP registration prevents brand theft.'
            ],
            [
                'pillar_id' => 6,
                'text' => 'Do you have a registered Board of Advisors or Directors?',
                'type' => 'Multiple Choice',
                'weight' => 10,
                'required' => false,
                'options' => [
                    ['label' => 'Formal Board (3+ members)', 'value' => 'Formal Board (3+ members)', 'points' => 10],
                    ['label' => 'Advisory informal', 'value' => 'Advisory informal', 'points' => 5],
                    ['label' => 'None', 'value' => 'None', 'points' => 0],
                ],
                'helper_text' => 'Governance is key for mid-to-large stage investment.'
            ],

            // Pillar 7: Data & Digital Maturity
            [
                'pillar_id' => 7,
                'text' => 'What software do you use for inventory and financial management?',
                'type' => 'Multiple Choice',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'Professional ERP (e.g. SAP, Odoo)', 'value' => 'Professional ERP (e.g. SAP, Odoo)', 'points' => 10],
                    ['label' => 'Cloud Accounting (Quickbooks)', 'value' => 'Cloud Accounting (Quickbooks)', 'points' => 7],
                    ['label' => 'Excel / Manual', 'value' => 'Excel / Manual', 'points' => 2],
                ],
                'helper_text' => 'Digital records provide transparency and easier auditing.'
            ],
            [
                'pillar_id' => 7,
                'text' => 'Do you collect customer data for marketing and sales analysis?',
                'type' => 'Yes/No',
                'weight' => 5,
                'required' => false,
                'options' => [
                    ['label' => 'Yes (CRM system)', 'value' => 'Yes (CRM system)', 'points' => 5],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Data-driven sales strategies improve ROI.'
            ],
            [
                'pillar_id' => 7,
                'text' => 'Is there a digital alert system for storage temperature variations?',
                'type' => 'Yes/No',
                'weight' => 10,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 10],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Digital monitoring for perishables prevents massive loss.'
            ],

            // Pillar 8: Growth & Scalability
            [
                'pillar_id' => 8,
                'text' => 'How easily can your product be distributed to a new city/province?',
                'type' => 'Multiple Choice',
                'weight' => 15,
                'required' => true,
                'options' => [
                    ['label' => 'Plug-and-play logistics', 'value' => 'Plug-and-play logistics', 'points' => 15],
                    ['label' => 'Requires facility setup', 'value' => 'Requires facility setup', 'points' => 7],
                    ['label' => 'Very difficult / Perishable', 'value' => 'Very difficult / Perishable', 'points' => 2],
                ],
                'helper_text' => 'Low friction expansion is highly attractive for growth funding.'
            ],
            [
                'pillar_id' => 8,
                'text' => 'Is your production facility already GMP or GHP certified?',
                'type' => 'Yes/No',
                'weight' => 15,
                'required' => true,
                'options' => [
                    ['label' => 'Yes', 'value' => 'Yes', 'points' => 15],
                    ['label' => 'No (Ongoing/None)', 'value' => 'No (Ongoing/None)', 'points' => 0],
                ],
                'helper_text' => 'Manufacturing certification is the gold standard for food growth.'
            ],
            [
                'pillar_id' => 8,
                'text' => 'Do you have a pipeline of new products ready to launch next year?',
                'type' => 'Yes/No',
                'weight' => 10,
                'required' => false,
                'options' => [
                    ['label' => 'Yes (3+ products)', 'value' => 'Yes (3+ products)', 'points' => 10],
                    ['label' => 'Yes (1-2 products)', 'value' => 'Yes (1-2 products)', 'points' => 5],
                    ['label' => 'No', 'value' => 'No', 'points' => 0],
                ],
                'helper_text' => 'Innovation pipeline ensures long-term market relevance.'
            ],
        ];

        foreach ($questions as $q) {
            Question::create(array_merge(['template_id' => $templateId], $q));
        }
    }
}
