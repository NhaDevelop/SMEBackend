<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\Request;

class SectorController extends Controller
{
    public function index()
    {
        return $this->success(Sector::all(), 'Sectors retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
        ]);

        $sector = Sector::create($validated);
        return $this->success($sector, 'Sector created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $sector = Sector::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
        ]);

        $sector->update($validated);
        return $this->success($sector, 'Sector updated successfully');
    }

    public function destroy($id)
    {
        $sector = Sector::findOrFail($id);
        $sector->delete();
        return $this->success(null, 'Sector deleted successfully');
    }
}
