<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\Question;
use Illuminate\Http\Request;

class AssessmentDebugController extends Controller
{
    /**
     * GET /api/admin/debug/assessment/{id}
     * Debug endpoint to verify assessment scoring calculation
     */
    public function debug($id)
    {
        $assessment = Assessment::with(['responses.question.pillar', 'smeProfile'])->findOrFail($id);
        
        $pillars = Pillar::all()->keyBy('id');
        $responses = $assessment->responses;
        
        // Step-by-step calculation
        $stepByStep = [];
        $pillarStats = [];
        
        foreach ($responses as $r) {
            $question = $r->question;
            if (!$question) continue;
            
            $pillarId = $question->pillar_id;
            $pillar = $pillars->get($pillarId);
            
            if (!isset($pillarStats[$pillarId])) {
                $pillarStats[$pillarId] = [
                    'pillar_name' => $pillar ? $pillar->name : 'Unknown',
                    'pillar_weight' => $pillar ? $pillar->weight : 0,
                    'earned' => 0,
                    'max' => 0,
                    'questions' => []
                ];
            }
            
            $pillarStats[$pillarId]['earned'] += (float)$r->score_awarded;
            $pillarStats[$pillarId]['max'] += (float)$question->weight;
            $pillarStats[$pillarId]['questions'][] = [
                'question_id' => $question->id,
                'question_text' => $question->text,
                'question_type' => $question->type,
                'answer' => $r->answer_value,
                'question_weight' => $question->weight,
                'score_awarded' => (float)$r->score_awarded,
                'percentage' => $question->weight > 0 ? round(($r->score_awarded / $question->weight) * 100, 2) : 0
            ];
        }
        
        // Calculate pillar scores and overall score
        $pillarBreakdown = [];
        $calculatedTotal = 0;
        
        foreach ($pillars as $pillar) {
            $stats = $pillarStats[$pillar->id] ?? ['earned' => 0, 'max' => 0, 'questions' => []];
            
            // Match original calculation exactly (no intermediate rounding)
            $pillarPercentage = $stats['max'] > 0 ? ($stats['earned'] / $stats['max']) * 100 : 0;
            $weightedContribution = ($pillarPercentage * $pillar->weight) / 100;
            $calculatedTotal += $weightedContribution;
            
            $pillarBreakdown[] = [
                'pillar_id' => $pillar->id,
                'pillar_name' => $pillar->name,
                'pillar_weight' => $pillar->weight,
                'earned_points' => $stats['earned'],
                'max_points' => $stats['max'],
                'pillar_percentage' => round($pillarPercentage, 2),  // For display only
                'weighted_contribution' => $weightedContribution,  // Raw value
                'questions_count' => count($stats['questions']),
                'questions' => $stats['questions']
            ];
        }
        
        // Verify against stored total_score
        $storedScore = (float)$assessment->total_score;
        $calculatedScore = round($calculatedTotal, 2);
        
        return response()->json([
            'assessment_id' => $assessment->id,
            'sme_name' => $assessment->smeProfile->company_name ?? 'N/A',
            'stored_total_score' => $storedScore,
            'calculated_total_score' => $calculatedScore,
            'difference' => round($storedScore - $calculatedScore, 2),
            'is_match' => abs($storedScore - $calculatedScore) < 0.01,
            'pillars_count' => $pillars->count(),
            'pillars_weight_sum' => $pillars->sum('weight'),
            'responses_count' => $responses->count(),
            'pillar_breakdown' => $pillarBreakdown,
            'calculation_summary' => [
                'formula' => 'SUM((earned/max) * 100 * pillar_weight / 100)',
                'step' => 'For each pillar: calculate % score, then multiply by pillar weight, sum all contributions'
            ]
        ], 200, [], JSON_PRETTY_PRINT);
    }
    
    /**
     * GET /api/admin/debug/pillars
     * Check pillar weights and totals
     */
    public function checkPillars()
    {
        $pillars = Pillar::all();
        
        return response()->json([
            'pillars' => $pillars->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'weight' => $p->weight
                ];
            }),
            'total_weight' => $pillars->sum('weight'),
            'pillars_count' => $pillars->count(),
            'is_valid' => abs($pillars->sum('weight') - 100) < 0.01
        ], 200, [], JSON_PRETTY_PRINT);
    }
    
    /**
     * GET /api/admin/debug/assessments/score-check
     * Check all assessments for score consistency
     */
    public function checkAllAssessments()
    {
        $assessments = Assessment::where('status', 'Completed')
            ->with('responses.question')
            ->get();
        
        $pillars = Pillar::all()->keyBy('id');
        $results = [];
        
        foreach ($assessments as $assessment) {
            // Recalculate
            $calculated = 0;
            $pillarStats = [];
            
            foreach ($assessment->responses as $r) {
                $q = $r->question;
                if (!$q) continue;
                
                $pid = $q->pillar_id;
                $pillar = $pillars->get($pid);
                
                if (!isset($pillarStats[$pid])) {
                    $pillarStats[$pid] = ['earned' => 0, 'max' => 0];
                }
                
                $pillarStats[$pid]['earned'] += (float)$r->score_awarded;
                $pillarStats[$pid]['max'] += (float)$q->weight;
            }
            
            foreach ($pillars as $p) {
                $stats = $pillarStats[$p->id] ?? ['earned' => 0, 'max' => 0];
                if ($stats['max'] > 0) {
                    $pct = ($stats['earned'] / $stats['max']) * 100;  // No rounding
                    $calculated += ($pct * $p->weight) / 100;  // Accumulate raw
                }
            }
            
            $stored = (float)$assessment->total_score;
            $calc = round($calculated, 2);  // Round only at end
            $diff = round($stored - $calc, 2);
            
            $results[] = [
                'assessment_id' => $assessment->id,
                'sme_id' => $assessment->sme_id,
                'stored' => $stored,
                'calculated' => $calc,
                'difference' => $diff,
                'match' => abs($diff) < 0.01
            ];
        }
        
        $mismatches = array_filter($results, fn($r) => !$r['match']);
        
        return response()->json([
            'total_assessments' => count($results),
            'matches' => count($results) - count($mismatches),
            'mismatches' => count($mismatches),
            'mismatch_details' => array_values($mismatches),
            'all_results' => $results
        ], 200, [], JSON_PRETTY_PRINT);
    }
}
