<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FrameworkSetting;
use App\Models\Pillar;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        return $this->success(Template::withCount(['questions'])->latest()->get(), 'Templates retrieved successfully');
    }

    public function active(Request $request)
    {
        $query = Template::where('status', 'Active')->withCount(['questions']);

        // Find all templates currently assigned to any program
        $assignedTemplateIds = \App\Models\Program::pluck('template_id')->filter()->unique()->toArray();

        // If a program_id is provided, allow its currently assigned template to remain in the list
        if ($request->has('program_id')) {
            $currentTemplateId = \App\Models\Program::where('id', $request->program_id)->value('template_id');
            if ($currentTemplateId) {
                $assignedTemplateIds = array_diff($assignedTemplateIds, [$currentTemplateId]);
            }
        }

        // Exclude the actively assigned templates
        if (!empty($assignedTemplateIds)) {
            $query->whereNotIn('id', $assignedTemplateIds);
        }

        return $this->success($query->get(), 'Active templates retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'nullable|string',
            'description' => 'nullable|string',
            'industry' => 'nullable|string',
            'status' => 'nullable|string',
            'settings' => 'nullable|array'
        ]);

        // 🔒 FAIRNESS SNAPSHOT: Bake the current Framework Settings into this template
        // at the moment of creation. This permanently stores the pillar weights (e.g.,
        // Financial = 20%) inside the template itself. Even if an Admin changes the
        // global Framework Settings tomorrow (to 40%), this template will always
        // score using the original 20% weight — for every assessment and every retake.
        if (empty($validated['settings'])) {
            $frameworkConfig = \App\Models\FrameworkSetting::where('key', 'framework_config')->first();
            if ($frameworkConfig) {
                $validated['settings'] = $frameworkConfig->value;
            } else {
                // Fallback: build from live pillars if no framework_config exists yet
                $validated['settings'] = [
                    'pillars' => Pillar::all()
                        ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'weight' => $p->weight])
                        ->toArray(),
                    'thresholds' => [
                        ['id' => 'investor', 'label' => 'Investor Ready', 'min' => 80, 'max' => 100, 'colorBg' => 'bg-emerald-500'],
                        ['id' => 'near', 'label' => 'Near Ready', 'min' => 60, 'max' => 79, 'colorBg' => 'bg-amber-500'],
                        ['id' => 'early', 'label' => 'Early Stage', 'min' => 40, 'max' => 59, 'colorBg' => 'bg-teal-500'],
                        ['id' => 'pre', 'label' => 'Pre-Investment', 'min' => 0, 'max' => 39, 'colorBg' => 'bg-red-500'],
                    ]
                ];
            }
        }

        $template = Template::create($validated);
        return $this->success($template, 'Template created successfully', 201);
    }

    public function show($id)
    {
        $template = Template::with(['questions'])->findOrFail($id);
        return $this->success($template);
    }

    public function update(Request $request, $id)
    {
        $template = Template::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'version' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'industry' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string',
            'pillars' => 'sometimes|array',
            'thresholds' => 'sometimes|array',
        ]);

        $updateData = [];
        if ($request->has('name'))
            $updateData['name'] = $validated['name'];
        if ($request->has('version'))
            $updateData['version'] = $validated['version'];
        if ($request->has('description'))
            $updateData['description'] = $validated['description'];
        if ($request->has('industry'))
            $updateData['industry'] = $validated['industry'];
        if ($request->has('status'))
            $updateData['status'] = $validated['status'];

        if ($request->has('pillars') || $request->has('thresholds')) {
            $settings = $template->settings ?? [];
            if ($request->has('pillars'))
                $settings['pillars'] = $validated['pillars'];
            if ($request->has('thresholds'))
                $settings['thresholds'] = $validated['thresholds'];
            $updateData['settings'] = $settings;
        }

        if (!empty($updateData)) {
            $template->update($updateData);
        }

        return $this->success($template, 'Template updated successfully');
    }

    public function duplicate(Request $request, $id)
    {
        $template = Template::with('questions')->findOrFail($id);

        $validated = $request->validate([
            'version' => 'nullable|string',
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|string'
        ]);

        $newTemplate = $template->replicate();
        if (isset($validated['version']))
            $newTemplate->version = $validated['version'];
        if (isset($validated['name']))
            $newTemplate->name = $validated['name'];
        if (isset($validated['status']))
            $newTemplate->status = $validated['status'];
        else
            $newTemplate->status = 'Draft';

        $newTemplate->push();

        // Duplicate questions
        foreach ($template->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->template_id = $newTemplate->id;
            $newQuestion->push();
        }

        $newTemplate->loadCount('questions');
        return $this->success($newTemplate, 'Template duplicated successfully', 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $template = Template::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:Draft,Active,Archived'
        ]);

        $template->update(['status' => $validated['status']]);

        return $this->success($template, "Template status updated to {$validated['status']}");
    }

    public function destroy($id)
    {
        $template = Template::findOrFail($id);

        // Security Check: Is it used in any Program?
        $isInProgram = \App\Models\Program::where('template_id', $id)->exists();
        if ($isInProgram) {
            return $this->error('Cannot delete: This template is currently assigned to one or more programs.', 422);
        }

        // Security Check: Are there any SME Assessments using this?
        $hasAssessments = \App\Models\Assessment::where('template_id', $id)->exists();
        if ($hasAssessments) {
            return $this->error('Cannot delete: This template has existing SME assessments. Archiving is recommended instead of deletion to preserve data history.', 422);
        }

        // 1. Mark as Archived (Logical status)
        $template->update(['status' => 'Archived']);

        // 2. Cascade Soft Delete to Questions
        $template->questions()->delete();

        // 3. Soft Delete the Template
        $template->delete();

        return $this->success(null, 'Template and its questions have been securely archived and soft-deleted.');
    }
}
