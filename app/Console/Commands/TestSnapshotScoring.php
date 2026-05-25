<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Program;
use App\Models\Template;
use App\Models\Pillar;
use App\Models\Question;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\User;
use App\Models\SmeProfile;
use App\Services\AssessmentService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestSnapshotScoring extends Command
{
    protected $signature   = 'test:snapshot-scoring';
    protected $description = 'Full end-to-end test of snapshot scoring architecture';

    public function handle(AssessmentService $service): void
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   ENTERPRISE SNAPSHOT SCORING — FULL TEST RUN   ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->info('');

        // ── STEP 1: Load seeded Program & Template ───────────────────────────────
        $program = Program::where('name', 'Snapshot Test Prox`gram v1')->first();
        if (!$program) {
            $this->error('❌ Program not found! Please run: php artisan db:seed --class=SnapshotTestSeeder');
            return;
        }
        $template = Template::find($program->template_id);
        $this->info("✅ Program loaded: [{$program->id}] {$program->name}");
        $this->info("✅ Template loaded: [{$template->id}] {$template->name}");

        // ── STEP 2: Show the snapshot taken at program creation ──────────────────
        $this->info('');
        $this->info('📸 Snapshot (rules locked at program creation):');
        $this->table(
            ['Pillar ID', 'Pillar Name', 'Weight (%)'],
            collect($program->scoring_snapshot)->map(fn($p) => [
                $p['id'], $p['name'], $p['weight']
            ])->toArray()
        );

        // ── STEP 3: Create a fake SME user ───────────────────────────────────────
        $smeUser = User::firstOrCreate(
            ['email' => 'test_sme_snap@sme.com'],
            [
                'full_name'   => 'Test SME User',
                'password'    => Hash::make('password123'),
                'role'        => 'SME',
                'is_verified' => 1,
                'status'      => 'ACTIVE',
            ]
        );
        $smeProfile = SmeProfile::firstOrCreate(
            ['user_id' => $smeUser->id],
            [
                'company_name'        => 'Test Company Ltd',
                'registration_number' => 'REG-999',
                'industry'            => 'Technology',
            ]
        );
        $this->info("✅ SME user ready: {$smeUser->email} (Profile ID: {$smeProfile->id})");

        // ── STEP 4: Load the 6 questions ─────────────────────────────────────────
        $questions = Question::where('template_id', $template->id)->get();
        $this->info("✅ {$questions->count()} questions loaded.");

        // ── STEP 5: Create the Assessment ────────────────────────────────────────
        // Remove old test assessment
        Assessment::where('sme_id', $smeProfile->id)
            ->where('program_id', $program->id)
            ->delete();

        $assessment = Assessment::create([
            'sme_id'      => $smeProfile->id,
            'template_id' => $template->id,
            'program_id'  => $program->id,
            'status'      => 'Completed',
            'completed_at'=> now(),
        ]);
        $this->info("✅ Assessment created: ID {$assessment->id}");

        // ── STEP 6: Simulate Answers (best-case perfect score) ───────────────────
        $this->info('');
        $this->info('📝 Simulating answers (aiming for MAXIMUM score)...');

        $responses   = [];
        $breakdown   = [];

        foreach ($questions as $question) {
            $scoreAwarded = 0;
            $answerLabel  = '';

            switch ($question->type) {

                case 'Scale (1-10)':
                    // Answer: 10/10 → 100% of weight
                    $answerValue  = 10;
                    $scoreAwarded = ((float) $answerValue / 10) * $question->weight;
                    $answerLabel  = "Scale: 10 → {$scoreAwarded}/{$question->weight} pts";
                    break;

                case 'Single Choice':
                    // Pick the option with the highest points
                    $best         = collect($question->options)->sortByDesc('points')->first();
                    $answerValue  = $best['label'];
                    $scoreAwarded = min((float) $question->weight, (float) ($best['points'] ?? 0));
                    $answerLabel  = "Single Choice: \"{$answerValue}\" → {$scoreAwarded}/{$question->weight} pts";
                    break;

                case 'Yes/No':
                    if (!empty($question->options)) {
                        // Yes/No with options (treated as Single Choice)
                        $best         = collect($question->options)->sortByDesc('points')->first();
                        $answerValue  = $best['label'];
                        $scoreAwarded = min((float) $question->weight, (float) ($best['points'] ?? 0));
                        $answerLabel  = "Yes/No (with options): \"{$answerValue}\" → {$scoreAwarded}/{$question->weight} pts";
                    } else {
                        // Plain Yes/No
                        $answerValue  = 'Yes';
                        $scoreAwarded = $question->weight;
                        $answerLabel  = "Yes/No: \"Yes\" → {$scoreAwarded}/{$question->weight} pts";
                    }
                    break;

                case 'Multiple Choice':
                    // Check ALL boxes → tests the cap (sum of all options may exceed weight)
                    $allLabels    = collect($question->options)->pluck('label')->toArray();
                    $runningScore = collect($question->options)->sum('points');
                    $scoreAwarded = min((float) $question->weight, $runningScore);
                    $answerValue  = $allLabels;
                    $answerLabel  = "Multiple Choice: all boxes → raw sum={$runningScore}, capped at {$scoreAwarded}/{$question->weight} pts";
                    break;

                case 'Dropdown Select':
                    // Pick the highest-scoring option
                    $best         = collect($question->options)->sortByDesc('points')->first();
                    $answerValue  = $best['label'];
                    $scoreAwarded = min((float) $question->weight, (float) ($best['points'] ?? 0));
                    $answerLabel  = "Dropdown: \"{$answerValue}\" → {$scoreAwarded}/{$question->weight} pts";
                    break;

                default:
                    $answerValue  = 'N/A';
                    $answerLabel  = "Unknown type: {$question->type}";
            }

            $responses[] = [
                'assessment_id' => $assessment->id,
                'question_id'   => $question->id,
                'answer_value'  => json_encode($answerValue),
                'score_awarded' => $scoreAwarded,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $breakdown[] = [
                $question->type,
                Str::limit($question->text, 40),
                $question->weight,
                $scoreAwarded,
                $answerLabel,
            ];
        }

        AssessmentResponse::insert($responses);
        $this->table(
            ['Type', 'Question', 'Max Weight', 'Scored', 'Answer Logic'],
            $breakdown
        );

        // ── STEP 7: Run the scoring engine ───────────────────────────────────────
        $this->info('');
        $this->info('⚙️  Running AssessmentService::calculatePillarScores()...');

        $thresholds  = $service->getThresholds($program->id);
        $pillarScores = $service->calculatePillarScores($assessment, $thresholds);
        $finalScore  = $service->calculateTotalScore($pillarScores);
        $finalScore  = min(100, round($finalScore, 2));

        // ── STEP 8: Show Pillar Breakdown ────────────────────────────────────────
        $this->info('');
        $this->info('📊 Pillar Score Breakdown:');
        $this->table(
            ['Pillar', 'Score (%)', 'Weight (%)', 'Weighted Contribution', 'Used Snapshot?'],
            collect($pillarScores)->map(function ($p) use ($program) {
                $snapIds   = collect($program->scoring_snapshot)->pluck('id')->toArray();
                $fromSnap  = in_array($p['id'], $snapIds) ? '✅ Yes' : '❌ No (Live)';
                $contrib   = round(($p['score'] * $p['weight']) / 100, 2);
                return [
                    $p['name'],
                    $p['score'] . '%',
                    $p['weight'] . '%',
                    $contrib . ' pts',
                    $fromSnap,
                ];
            })->toArray()
        );

        // ── STEP 9: Final Score ───────────────────────────────────────────────────
        $this->info('');
        $this->info("🏆 FINAL SCORE: {$finalScore} / 100");

        // ── STEP 10: Prove Snapshot Protection ───────────────────────────────────
        $this->info('');
        $this->info('🔬 SNAPSHOT PROTECTION TEST:');
        $this->info('   Changing "Snap Finance" pillar weight from 60% → 99% in live DB...');

        $snapPillar = Pillar::where('name', 'Snap Finance')->first();
        $snapPillar->update(['weight' => 99]);

        // Clear cached pillar scores to force recalculation
        $assessment->update(['pillar_scores' => null]);
        $assessment->refresh();

        $pillarScoresAfter = $service->calculatePillarScores($assessment, $thresholds);
        $finalScoreAfter   = min(100, round($service->calculateTotalScore($pillarScoresAfter), 2));

        $this->info("   ✅ Score BEFORE weight change: {$finalScore}");
        $this->info("   ✅ Score AFTER  weight change: {$finalScoreAfter}");

        if ($finalScore === $finalScoreAfter) {
            $this->info('');
            $this->info('   ✅✅✅ SNAPSHOT IS WORKING CORRECTLY!');
            $this->info('   The live pillar weight change had ZERO effect on this program.');
        } else {
            $this->error('   ❌ SNAPSHOT FAILED — Score changed when live weight was updated!');
        }

        // Restore the weight
        $snapPillar->update(['weight' => 60]);
        $this->info('   🔄 Pillar weight restored to 60%.');

        $this->info('');
        $this->info('═══════════════════════════════════════════════════');
        $this->info('   TEST COMPLETE ✅');
        $this->info('═══════════════════════════════════════════════════');
    }
}
