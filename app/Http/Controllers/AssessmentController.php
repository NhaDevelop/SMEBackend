<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Question;
use App\Models\Template;
use App\Models\SmeProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    public function getQuestions(Request $request)
    {
        // For now, get the first active template or a specific one if provided
        $template = Template::where('status', 'Active')->first();
        if (!$template) {
            return $this->error('No active assessment template found', 404);
        }

        $questions = Question::where('template_id', $template->id)->get();
        return $this->success([
            'template' => $template,
            'questions' => $questions
        ], 'Questions retrieved successfully');
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:templates,id',
            'program_id'  => 'nullable|exists:programs,id'
        ]);

        $user = auth()->user();
        // Explicitly load the smeProfile relationship to avoid 403 on lazy-load miss
        $user->load('smeProfile');

        if (!$user->smeProfile) {
            return $this->forbidden('SME profile not found. Please complete your profile before starting an assessment.');
        }

        // Check if the program associated with this template has expired
        // If program_id is provided, use it; otherwise, fall back to matching by template_id
        $program = null;
        if (!empty($validated['program_id'])) {
            $program = \App\Models\Program::find($validated['program_id']);
        } else {
            $program = \App\Models\Program::where('template_id', $validated['template_id'])->first();
        }

        if ($program) {
            if ($program->isFinished()) {
                return $this->forbidden('This program has ended. The assessment period is now closed and no new assessments can be started.');
            }
            // Also verify the SME is still enrolled and their enrollment is valid
            $enrollment = \App\Models\ProgramEnrollment::where('program_id', $program->id)
                ->where('sme_id', $user->smeProfile->id)
                ->first();
            if (!$enrollment) {
                return $this->forbidden('You are not enrolled in the program associated with this assessment template.');
            }
        }

        $assessment = Assessment::create([
            'sme_id'      => $user->smeProfile->id,
            'template_id' => $validated['template_id'],
            'program_id'  => $program ? $program->id : null,
            'status'      => 'In Progress',
            'started_at'  => now()
        ]);

        return $this->success([
            'assessment_id' => $assessment->id
        ], 'Assessment session initialized', 201);
    }

    public function submit(Request $request, $id)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.value' => 'required'
        ]);

        $user = auth()->user();
        // Explicitly load the smeProfile relationship to prevent null access
        $user->load('smeProfile');

        if (!$user->smeProfile) {
            return $this->forbidden('SME profile not found. Please complete your profile before submitting an assessment.');
        }

        $assessment = Assessment::where('sme_id', $user->smeProfile->id)->findOrFail($id);
        $template = Template::findOrFail($assessment->template_id);

        // --- NEW: Security Check — is the program associated with this template finished? ---
        $program = \App\Models\Program::where('template_id', $template->id)->first();
        if ($program && $program->isFinished()) {
            return $this->forbidden('This assessment can no longer be submitted because the associated program has ended.');
        }
        // --- END SECURITY CHECK ---

        $questions = Question::where('template_id', $template->id)->get()->keyBy('id');

        return DB::transaction(function () use ($assessment, $template, $questions, $validated) {
            $assessment->update([
                'status' => 'Completed',
                'questions_snapshot' => $questions->toArray(),
                'completed_at' => now()
            ]);

            $pillars = \App\Models\Pillar::get()->keyBy('id');
            $pillarStats = []; // [pillarId => ['earned' => 0, 'max' => 0]]

            $responses = [];
            foreach ($validated['answers'] as $answerData) {
                $question = $questions->get($answerData['question_id']);
                if (!$question)
                    continue;

                $scoreAwarded = 0;
                $pillarId = $question->pillar_id;

                if (!isset($pillarStats[$pillarId])) {
                    $pillarStats[$pillarId] = ['earned' => 0, 'max' => 0];
                }

                // Max points for this question
                $pillarStats[$pillarId]['max'] += $question->weight;

                // Points earned
                if ($question->type === 'Yes/No' && ($answerData['value'] === true || $answerData['value'] === 'true' || $answerData['value'] === 'Yes')) {
                    $scoreAwarded = $question->weight;
                }
                elseif ($question->type === 'Scale (1-10)') {
                    $scoreAwarded = ($answerData['value'] / 10) * $question->weight;
                }
                elseif (in_array($question->type, ['Multiple Choice', 'Single Choice', 'Dropdown Select'])) {
                    $options = collect($question->options);
                    $selectedLabel = $answerData['value'];
                    $option = $options->firstWhere('label', $selectedLabel);
                    if ($option) {
                        // Level 3: Option Weighting (Treat points as percentage 0-100 of the question weight)
                        $optionPoints = data_get($option, 'points', 0);
                        $scoreAwarded = ($optionPoints / 100) * $question->weight;
                    }
                }

                $pillarStats[$pillarId]['earned'] += $scoreAwarded;

                $responses[] = [
                    'assessment_id' => $assessment->id,
                    'question_id' => $question->id,
                    'answer_value' => json_encode($answerData['value']),
                    'score_awarded' => $scoreAwarded,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            AssessmentResponse::insert($responses);

            // Final Weighted Score Calculation
            $finalScore = 0;
            foreach ($pillarStats as $pillarId => $stats) {
                if ($stats['max'] > 0) {
                    $pillar = $pillars->get($pillarId);
                    $pillarWeight = $pillar ? $pillar->weight : 0;

                    // (Earned in Pillar / Max in Pillar) * Global Pillar Weight
                    $pillarPercentage = ($stats['earned'] / $stats['max']) * 100;
                    $weightedContribution = ($pillarPercentage * $pillarWeight) / 100;

                    $finalScore += $weightedContribution;
                }
            }

            // Cap at 100 just in case
            $finalScore = min(100, round($finalScore, 2));

            $assessment->update(['total_score' => $finalScore]);

            // Update SME Profile score
            $smeProfile = SmeProfile::find($assessment->sme_id);
            if ($smeProfile) {
                $smeProfile->update(['readiness_score' => $finalScore]);
            }

            return $this->success([
                'assessment_id' => $assessment->id,
                'total_score' => $finalScore,
                'pillar_breakdown' => $pillarStats
            ], 'Assessment submitted successfully');
        });
    }

    public function history()
    {
        $user = auth()->user();
        if (!$user->smeProfile) {
            return $this->success([], 'No history found');
        }
        $assessments = Assessment::with(['template'])
            ->where('sme_id', $user->smeProfile->id)
            ->latest()
            ->get();

        $history = $assessments->map(function ($assessment, $index) use ($assessments) {
            $pillars = \App\Models\Pillar::all();
            $responses = \App\Models\AssessmentResponse::where('assessment_id', $assessment->id)
                ->with('question')
                ->get();

            $grouped = [];
            foreach ($responses as $r) {
                if (!$r->question) continue;
                $pid = $r->question->pillar_id;
                if (!isset($grouped[$pid])) {
                    $grouped[$pid] = ['earned' => 0, 'max' => 0];
                }
                $grouped[$pid]['earned'] += (float)$r->score_awarded;
                $grouped[$pid]['max'] += (float)$r->question->weight;
            }

            $pillarStats = [];
            foreach ($pillars as $p) {
                $data = $grouped[$p->id] ?? ['earned' => 0, 'max' => 0];
                $score = $data['max'] > 0 ? round(($data['earned'] / $data['max']) * 100, 1) : 0;
                $pillarStats[] = [
                    'name' => $p->name,
                    'score' => $score
                ];
            }

            // Sort by score descending to get top pillars
            $sortedPillars = collect($pillarStats)->sortByDesc('score')->values()->all();

            $change = null;
            if (isset($assessments[$index + 1])) {
                $prev = $assessments[$index + 1];
                $change = round($assessment->total_score - $prev->total_score, 1);
            }

            $templateName = $assessment->template ? $assessment->template->name : ($assessment->template_name ?? 'Standard Investment Readiness Assessment');

            return [
                'id' => $assessment->id,
                'name' => $templateName,
                'date' => $assessment->completed_at ? $assessment->completed_at->format('F j, Y') : $assessment->created_at->format('F j, Y'),
                'score' => (float)$assessment->total_score,
                'change' => $change,
                'isLatest' => $index === 0,
                'topPillars' => array_map(function($p) {
                    return [
                        'name' => explode(' ', trim($p['name']))[0],
                        'score' => $p['score']
                    ];
                }, array_slice($sortedPillars, 0, 4)),
                'pillars' => $pillarStats
            ];
        });

        return $this->success($history, 'Assessment history retrieved successfully');
    }
}