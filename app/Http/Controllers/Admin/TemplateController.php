<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        return $this->success(Template::withCount(['questions'])->get(), 'Templates retrieved successfully');
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
            'status' => 'nullable|string',
            'settings' => 'nullable|array'
        ]);

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
            'pillars' => 'required|array',
            'thresholds' => 'required|array',
        ]);

        $template->update([
            'settings' => [
                'pillars' => $validated['pillars'],
                'thresholds' => $validated['thresholds']
            ]
        ]);

        return $this->success($template, 'Template settings updated');
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
