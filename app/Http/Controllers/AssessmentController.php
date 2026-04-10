<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Question;
use App\Models\Template;
use App\Models\SmeProfile;
use App\Services\AssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    protected $assessmentService;

    public function __construct(AssessmentService $assessmentService)
    {
        $this->assessmentService = $assessmentService;
    }
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

                $extractedValue = is_array($answerData['value']) 
                    ? ($answerData['value']['label'] ?? $answerData['value']['value'] ?? json_encode($answerData['value'])) 
                    : $answerData['value'];

                // Points earned
                if ($question->type === 'Yes/No' && empty($question->options)) {
                    if ($extractedValue === true || $extractedValue === 'true' || $extractedValue === 'Yes') {
                        $scoreAwarded = $question->weight;
                    }
                }
                elseif ($question->type === 'Scale (1-10)') {
                    $scoreAwarded = ((float)$extractedValue / 10) * $question->weight;
                }
                else {
                    // Treat Multiple Choice, Single Choice, Dropdown Select, AND Yes/No with options the same
                    $options = collect($question->options);
                    $option = $options->firstWhere('label', $extractedValue);
                    if ($option) {
                        // Level 3: Option Weighting (Treat points as percentage 0-100 of the question weight)
                        $optionPoints = data_get($option, 'points', 0);
                        $scoreAwarded = ($optionPoints / 100) * $question->weight;
                    } elseif ($extractedValue === true || $extractedValue === 'true' || $extractedValue === 'Yes') {
                        // Fallback in case it's a Yes/No type but the options were malformed
                        $scoreAwarded = $question->weight;
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

            // REFACTORED: Use AssessmentService for final score calculation
            $thresholds = $this->assessmentService->getThresholds($assessment->program_id);
            $pillarScores = $this->assessmentService->calculatePillarScores($assessment, $thresholds);
            $finalScore = $this->assessmentService->calculateTotalScore($pillarScores);

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
            $thresholds = $this->assessmentService->getThresholds($assessment->program_id);
            $pillarStats = $this->assessmentService->calculatePillarScores($assessment, $thresholds);
            $sortedPillars = $this->assessmentService->getTopPillars($pillarStats, 4);

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
                'topPillars' => $sortedPillars,
                'pillars' => $pillarStats
            ];
        });

        return $this->success($history, 'Assessment history retrieved successfully');
    }
}