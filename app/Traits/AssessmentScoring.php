<?php

namespace App\Traits;

use App\Models\AssessmentResponse;
use App\Models\Pillar;
use App\Models\FrameworkSetting;

trait AssessmentScoring
{
    /**
     * Get risk label based on score and thresholds.
     */
    protected function getRiskLabel($score, $thresholds = null)
    {
        if (!$thresholds) {
            $settings = FrameworkSetting::where('key', 'framework_config')->first();
            $thresholds = $settings ? ($settings->value['thresholds'] ?? []) : [
                ['id' => 'investor', 'label' => 'Investor Ready', 'min' => 80, 'max' => 100],
                ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79],
                ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59],
                ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39],
            ];
        }

        foreach ($thresholds as $t) {
            if ($score >= $t['min'] && $score <= $t['max']) {
                return $t['label'];
            }
        }
        
        // Fallback
        if ($score >= 80) return 'Investor Ready';
        if ($score >= 60) return 'Near Ready';
        if ($score >= 40) return 'Early Stage';
        return 'Pre-Investment';
    }

    /**
     * Calculate pillar scores for a specific assessment.
     */
    protected function calculatePillarScores($assessment, $thresholds = null)
    {
        if (!$assessment) return [];

        if (!$thresholds) {
            $settings = FrameworkSetting::where('key', 'framework_config')->first();
            $thresholds = $settings ? ($settings->value['thresholds'] ?? []) : [
                ['id' => 'investor', 'label' => 'Investor Ready', 'min' => 80, 'max' => 100],
                ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79],
                ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59],
                ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39],
            ];
        }

        $pillars = Pillar::all();
        
        // Use eager loaded responses if available, or fetch them
        $responses = $assessment->relationLoaded('responses') 
            ? $assessment->responses 
            : AssessmentResponse::where('assessment_id', $assessment->id)->with('question')->get();

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

        $stats = [];
        foreach ($pillars as $p) {
            $data = $grouped[$p->id] ?? ['earned' => 0, 'max' => 0];
            $score = $data['max'] > 0 ? round(($data['earned'] / $data['max']) * 100, 1) : 0;

            $stats[] = [
                'id' => $p->id,
                'name' => $p->name,
                'score' => $score,
                'weight' => (float)$p->weight,
                'riskLevel' => $this->getRiskLabel($score, $thresholds)
            ];
        }

        return $stats;
    }

    /**
     * Calculate top recommended actions for an assessment based on low-scoring indicators
     */
    protected function calculateTopActions($assessment, $limit = 5)
    {
        if (!$assessment) return [];

        $responses = $assessment->relationLoaded('responses') 
            ? $assessment->responses 
            : AssessmentResponse::where('assessment_id', $assessment->id)->with('question')->get();

        $pillars = Pillar::all()->keyBy('id');

        $gaps = $responses->filter(function ($r) {
            return $r->question && $r->question->weight > 0 && ($r->score_awarded / $r->question->weight) <= 0.5;
        })->map(function ($r) use ($pillars) {
            $pModel = $pillars->get($r->question->pillar_id);
            $pillarName = $pModel ? $pModel->name : $r->question->pillar_id;
            
            $max = (float)$r->question->weight;
            $earned = (float)$r->score_awarded;
            $ratio = $max > 0 ? ($earned / $max) : 1;
            
            return [
                'id' => 'gap_' . $r->id,
                'title' => 'Improve: ' . $r->question->text,
                'description' => 'Your current score for this indicator is ' . round($ratio * 100) . '%. Addressing this gap could improve your overall readiness by ' . round($max - $earned, 1) . ' points.',
                'priority' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                'pillarRisk' => $ratio <= 0.2 ? 'high' : ($ratio <= 0.5 ? 'medium' : 'low'),
                'pillar' => $pillarName,
                'impact' => round(($max - $earned), 1),
                'points' => round(($max - $earned), 1),
                'status' => 'pending'
            ];
        })->sortByDesc('impact')->values();

        if ($limit) {
            return $gaps->take($limit)->toArray();
        }

        return $gaps->toArray();
    }
}
