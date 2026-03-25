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
}
