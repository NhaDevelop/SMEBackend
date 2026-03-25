<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (!$user->smeProfile) {
            return $this->success([], 'No documents found');
        }
        $documents = Document::where('sme_id', $user->smeProfile->id)->latest()->get();
        return $this->success($documents, 'Documents retrieved successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|max:10240',
            'category'    => 'nullable|string',
            'description' => 'nullable|string',
            'type'        => 'nullable|string|in:PITCH_DECK,FINANCIALS,LEGAL'
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/' . auth()->id());

        $document = Document::create([
            'sme_id'            => auth()->user()->smeProfile->id,
            'name'              => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'type'              => $request->type,
            'category'          => $request->category,
            'description'       => $request->description,
            'size'              => $file->getSize(),
            'file_url'          => Storage::url($path),
            'is_verified'       => false,
            'uploaded_at'       => now()
        ]);

        return $this->success($document, 'Document uploaded successfully', 201);
    }

    public function show($id)
    {
        $document = Document::where('sme_id', auth()->user()->smeProfile->id)
            ->findOrFail($id);
        return $this->success($document);
    }

    public function destroy($id)
    {
        $document = Document::where('sme_id', auth()->user()->smeProfile->id)
            ->findOrFail($id);

        // Remove file from storage
        $storagePath = str_replace('/storage/', 'public/', $document->file_url);
        Storage::delete($storagePath);

        $document->delete();

        return $this->success(null, 'Document deleted successfully');
    }
}
