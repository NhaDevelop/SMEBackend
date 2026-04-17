<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FrameworkSetting;
use App\Models\Pillar;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class SettingController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $settings = FrameworkSetting::where('key', 'framework_config')->first();
        
        if (!$settings) {
            // Return defaults if not set yet
            return $this->success([
                'pillars' => Pillar::all(),
                'thresholds' => [
                    ['id' => 'investor', 'label' => 'Investor Ready', 'min' => 80, 'max' => 100, 'colorBg' => 'bg-emerald-500'],
                    ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79, 'colorBg' => 'bg-amber-500'],
                    ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59, 'colorBg' => 'bg-teal-500'],
                    ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39, 'colorBg' => 'bg-red-500'],
                ]
            ]);
        }

        return $this->success($settings->value);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pillars' => 'sometimes|array',
            'thresholds' => 'sometimes|array',
        ]);

        $settings = FrameworkSetting::firstOrCreate(
            ['key' => 'framework_config'],
            ['value' => ['pillars' => Pillar::all()->toArray(), 'thresholds' => [
                ['id' => 'investor', 'label' => 'Investor Ready', 'min' => 80, 'max' => 100, 'colorBg' => 'bg-emerald-500'],
                ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79, 'colorBg' => 'bg-amber-500'],
                ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59, 'colorBg' => 'bg-teal-500'],
                ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39, 'colorBg' => 'bg-red-500'],
            ]]]
        );
        $newValue = $settings->value ?? [];

        if (isset($validated['thresholds'])) {
            // Validate Thresholds (Contiguous, 0-100, No overlaps)
            $ths = $validated['thresholds'];
            usort($ths, function($a, $b) {
                return $a['min'] <=> $b['min'];
            });

            // 1. Must start at 0
            if ($ths[0]['min'] != 0) {
                return $this->error("Thresholds must start at 0 (found {$ths[0]['min']})", 422);
            }

            // 2. Must end at 100
            if (end($ths)['max'] != 100) {
                return $this->error("Thresholds must end at 100 (found " . end($ths)['max'] . ")", 422);
            }

            // 3. Must be contiguous
            for ($i = 0; $i < count($ths) - 1; $i++) {
                if ($ths[$i]['max'] != $ths[$i+1]['min'] - 1) {
                    return $this->error("Gaps or overlaps detected between '{$ths[$i]['label']}' ({$ths[$i]['min']}-{$ths[$i]['max']}) and '{$ths[$i+1]['label']}' ({$ths[$i+1]['min']}-{$ths[$i+1]['max']}). Ranges must be contiguous (e.g., 0-39, 40-59).", 422);
                }
            }
            $newValue['thresholds'] = $ths;
        }

        if (isset($validated['pillars'])) {
            $newValue['pillars'] = $validated['pillars'];
            // Also update individual Pillar weights in the pillars table for consistency
            foreach ($validated['pillars'] as $pData) {
                if (isset($pData['id']) && is_numeric($pData['id'])) {
                    Pillar::where('id', $pData['id'])->update(['weight' => $pData['weight']]);
                }
            }
        }

        $settings->update(['value' => $newValue]);

        return $this->success($settings->value, 'Settings updated successfully');
    }
}
