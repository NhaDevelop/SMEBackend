<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $query = Question::query();
        
        if ($request->filled('template_id')) {
            $query->where('template_id', '=', $request->template_id);
        }

        return $this->success($query->get(), 'Questions retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:templates,id',
            'pillar_id'   => 'required|string',
            'text'        => 'required|string',
            'type'        => 'required|string',
            'weight'      => 'required|numeric|min:0|max:100',
            'required'    => 'boolean',
            'options'     => 'nullable|array',
            'helper_text' => 'nullable|string',
        ]);

        if (!$this->validatePillarWeight($validated['template_id'], $validated['pillar_id'], $validated['weight'])) {
            return $this->error('Total pillar weight cannot exceed 100%.', 422);
        }

        $question = Question::create($validated);
        return $this->success($question, 'Question created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        $validated = $request->validate([
            'text'        => 'sometimes|string',
            'type'        => 'sometimes|string',
            'weight'      => 'sometimes|numeric|min:0|max:100',
            'required'    => 'sometimes|boolean',
            'options'     => 'nullable|array',
            'helper_text' => 'nullable|string',
        ]);

        if (isset($validated['weight'])) {
            if (!$this->validatePillarWeight($question->template_id, $question->pillar_id, $validated['weight'], $question->id)) {
                return $this->error('Total pillar weight cannot exceed 100%.', 422);
            }
        }

        $question->update($validated);
        return $this->success($question, 'Question updated successfully');
    }

    public function destroy(Request $request)
    {
        // Frontend uses DELETE /admin/questions?id=...
        $id = $request->query('id');
        $question = Question::findOrFail($id);
        $question->delete();
        return $this->success(null, 'Question deleted successfully');
    }

    private function validatePillarWeight($templateId, $pillarId, $newWeight, $ignoreId = null)
    {
        $query = Question::where('template_id', $templateId)
            ->where('pillar_id', $pillarId);

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        $currentTotal = $query->sum('weight');

        return ($currentTotal + $newWeight) <= 100;
    }
}
