<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PillarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return $this->success(\App\Models\Pillar::all(), 'Pillars retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0|max:100',
        ]);

        $currentTotal = \App\Models\Pillar::sum('weight');
        if ($currentTotal + $validated['weight'] > 100) {
            return $this->error('Total weight cannot exceed 100%', 422);
        }

        $pillar = \App\Models\Pillar::create($validated);
        return $this->success($pillar, 'Pillar created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return $this->success(\App\Models\Pillar::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $pillar = \App\Models\Pillar::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'weight' => 'sometimes|numeric|min:0|max:100',
        ]);

        if (isset($validated['weight'])) {
            $otherPillarsWeight = \App\Models\Pillar::where('id', '!=', $id)->sum('weight');
            if ($otherPillarsWeight + $validated['weight'] > 100) {
                return $this->error('Total weight cannot exceed 100%', 422);
            }
        }

        $pillar->update($validated);
        return $this->success($pillar, 'Pillar updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pillar = \App\Models\Pillar::findOrFail($id);
        $pillar->delete();
        return $this->success(null, 'Pillar deleted successfully');
    }
}
