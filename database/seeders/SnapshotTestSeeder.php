<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Template;
use App\Models\Pillar;
use App\Models\Question;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SnapshotTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Starting Snapshot Test Seeder...');

        // ─── 1. Create Admin user ────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin_snap@sme.com'],
            [
                'full_name'  => 'Snap Admin',
                'password'   => Hash::make('password123'),
                'role'       => 'ADMIN',
                'is_verified'=> 1,
            ]
        );
        $this->command->info('✅ Admin user ready: ' . $admin->email);

        // ─── 2. Clear previous test data ────────────────────────────────────────
        Program::where('name', 'Snapshot Test Program v1')->delete();
        Template::where('name', 'Snapshot Test Template')->delete();
        Pillar::whereIn('name', ['Snap Finance', 'Snap Operations'])->delete();
        $this->command->info('🗑  Old test data cleared.');

        // ─── 3. Create 2 Pillars with weights totaling 100% ─────────────────────
        $pillarA = Pillar::create(['name' => 'Snap Finance',    'weight' => 60]);
        $pillarB = Pillar::create(['name' => 'Snap Operations', 'weight' => 40]);
        $this->command->info("✅ Pillars created: {$pillarA->name} (60%) & {$pillarB->name} (40%)");

        // ─── 4. Create the Template ──────────────────────────────────────────────
        $template = Template::create([
            'name'        => 'Snapshot Test Template',
            'description' => 'Full test of all question types + snapshot architecture',
            'status'      => 'Active',
            'version'     => '1.0',
        ]);
        $this->command->info("✅ Template created: {$template->name} (ID: {$template->id})");

        // ─── 5. Create 3 Questions per Pillar (all 4 question types covered) ────

        // PILLAR A — Finance (weight: 60 total)
        // Question A1: Scale (1-10) — weight: 20
        Question::create([
            'template_id' => $template->id,
            'pillar_id'   => $pillarA->id,
            'text'        => 'Rate your annual revenue growth on a scale of 1 to 10.',
            'type'        => 'Scale (1-10)',
            'weight'      => 20,
            'required'    => true,
            'options'     => null,
            'helper_text' => 'A higher score indicates stronger revenue momentum.',
        ]);

        // Question A2: Single Choice — weight: 20
        Question::create([
            'template_id' => $template->id,
            'pillar_id'   => $pillarA->id,
            'text'        => 'What best describes your current profitability?',
            'type'        => 'Single Choice',
            'weight'      => 20,
            'required'    => true,
            'options'     => [
                ['label' => 'Highly profitable (>20% margin)', 'points' => 20],
                ['label' => 'Profitable (1-20% margin)',        'points' => 14],
                ['label' => 'Breaking even',                    'points' => 7],
                ['label' => 'Currently losing money',           'points' => 0],
            ],
            'helper_text' => 'Profitability indicates financial sustainability.',
        ]);

        // Question A3: Yes/No (standard, no options) — weight: 20
        Question::create([
            'template_id' => $template->id,
            'pillar_id'   => $pillarA->id,
            'text'        => 'Do you have audited financial statements for the last 2 years?',
            'type'        => 'Yes/No',
            'weight'      => 20,
            'required'    => true,
            'options'     => [],
            'helper_text' => 'Audited financials increase credibility with investors.',
        ]);

        // PILLAR B — Operations (weight: 40 total)
        // Question B1: Yes/No with custom options (counts as Single Choice fallback) — weight: 15
        Question::create([
            'template_id' => $template->id,
            'pillar_id'   => $pillarB->id,
            'text'        => 'Is your business legally registered with government authorities?',
            'type'        => 'Yes/No',
            'weight'      => 15,
            'required'    => true,
            'options'     => [
                ['label' => 'Yes', 'points' => 15],
                ['label' => 'No',  'points' => 0],
            ],
            'helper_text' => 'Legal registration is a fundamental compliance requirement.',
        ]);

        // Question B2: Multiple Choice (checkboxes) — weight: 15 (testing cap: options sum to 20+)
        Question::create([
            'template_id' => $template->id,
            'pillar_id'   => $pillarB->id,
            'text'        => 'Which of the following compliance documents does your business hold?',
            'type'        => 'Multiple Choice',
            'weight'      => 15,
            'required'    => false,
            'options'     => [
                ['label' => 'Tax Patent',       'points' => 5],
                ['label' => 'MoC Certificate',  'points' => 5],
                ['label' => 'ISO 9001',         'points' => 5],
                ['label' => 'Business Permit',  'points' => 5],
                // Sum of all = 20, but max per question = 15 → tests the cap
            ],
            'helper_text' => 'Holding multiple compliance documents reduces regulatory risk.',
        ]);

        // Question B3: Dropdown Select — weight: 10
        Question::create([
            'template_id' => $template->id,
            'pillar_id'   => $pillarB->id,
            'text'        => 'How many full-time employees does your business currently employ?',
            'type'        => 'Dropdown Select',
            'weight'      => 10,
            'required'    => true,
            'options'     => [
                ['label' => '1 - 5 employees',    'points' => 2],
                ['label' => '6 - 20 employees',   'points' => 5],
                ['label' => '21 - 50 employees',  'points' => 8],
                ['label' => '51+ employees',       'points' => 10],
            ],
            'helper_text' => 'Workforce size indicates operational capacity.',
        ]);

        $this->command->info('✅ 6 Questions created (3 per pillar, all types covered).');

        // ─── 6. Take Snapshot & Create Program ──────────────────────────────────
        $snapshot = Pillar::all()->toArray();

        $program = Program::create([
            'name'               => 'Snapshot Test Program v1',
            'slug'               => Str::slug('Snapshot Test Program v1'),
            'description'        => 'Program created to test the scoring_snapshot enterprise feature.',
            'template_id'        => $template->id,
            'status'             => 'Published',
            'start_date'         => Carbon::now()->subDay(),
            'end_date'           => Carbon::now()->addDays(30),
            'scoring_snapshot'   => $snapshot,
            'created_by_user_id' => $admin->id,
        ]);

        $this->command->info("✅ Program created: {$program->name} (ID: {$program->id})");
        $this->command->info('📸 Scoring snapshot saved. Pillars in snapshot:');
        foreach ($snapshot as $p) {
            $this->command->line("    - {$p['name']} | Weight: {$p['weight']}%");
        }

        $this->command->info('');
        $this->command->info('🎉 Snapshot Test Seeder finished!');
        $this->command->info("📌 Template ID: {$template->id}  |  Program ID: {$program->id}");
        $this->command->info("📌 Pillar A (Finance): {$pillarA->id}  |  Pillar B (Ops): {$pillarB->id}");
    }
}
