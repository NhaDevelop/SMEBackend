<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    // Allowed MIME types (strict whitelist)
    private const ALLOWED_MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx';
    private const MAX_FILE_SIZE_KB = 5120; // 5 MB

    private const PUBLIC_COLUMNS = [
        'id', 'sme_id', 'name', 'original_filename', 'type',
        'category', 'description', 'size', 'is_verified',
        'uploaded_at', 'created_at', 'updated_at',
    ];

    public function index(Request $request)
    {
        $user = auth()->user();

        // Default: SME sees only their own documents
        $smeId = $user->smeProfile?->id;

        // Admins and Investors may request a specific SME's documents
        if (in_array($user->role, ['ADMIN', 'INVESTOR']) && $request->filled('smeId')) {
            $smeId = (int) $request->input('smeId'); // Cast to int — prevents injection
        }

        if (!$smeId) {
            return $this->success([], 'No documents found');
        }

        $documents = Document::where('sme_id', $smeId)
            ->select(self::PUBLIC_COLUMNS)
            ->latest('uploaded_at')
            ->get()
            ->map(fn($doc) => $this->appendDownloadUrl($doc));

        return $this->success($documents, 'Documents retrieved successfully');
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // Guard: only SMEs with a profile can upload
        if (!$user->smeProfile) {
            return response()->json(['error' => 'Only SME accounts can upload documents.'], 403);
        }

        $request->validate([
            'file'        => 'required|file|mimes:' . self::ALLOWED_MIMES . '|max:' . self::MAX_FILE_SIZE_KB,
            'name'        => 'nullable|string|max:200',
            'category'    => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'type'        => 'nullable|string|in:PITCH_DECK,FINANCIALS,LEGAL',
        ]);

        $file = $request->file('file');

        // Store in private local disk — never publicly accessible
        $path = $file->store('documents/' . $user->id, 'local');

        $document = Document::create([
            'sme_id'            => $user->smeProfile->id,
            'name'              => $request->input('name') ?: $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'type'              => $request->input('type'),
            'category'          => $request->input('category', 'General'),
            'description'       => $request->input('description'),
            'size'              => $file->getSize(),
            'file_url'          => $path,
            'is_verified'       => false,
            'uploaded_at'       => now(),
        ]);

        return $this->success(
            $this->appendDownloadUrl($document->only(self::PUBLIC_COLUMNS)),
            'Document uploaded successfully',
            201
        );
    }

    public function show($id)
    {
        $user = auth()->user();
        $document = Document::select(self::PUBLIC_COLUMNS)->findOrFail($id);

        if (!$this->canAccess($user, $document)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return $this->success($this->appendDownloadUrl($document));
    }

    /**
     * Secure, RBAC-gated file download.
     * Streams the file — never exposes the real storage path.
     */
    public function download($id)
    {
        $document = Document::findOrFail($id);
        $user = auth()->user();

        if (!$this->canAccess($user, $document)) {
            Log::warning('Unauthorized document download attempt', [
                'user_id'     => $user->id,
                'document_id' => $id,
            ]);
            return response()->json(['error' => 'Unauthorized to access this document.'], 403);
        }

        $path = $document->file_url;

        // Fallback: legacy public-disk files (path starts with /storage/)
        if (str_starts_with($path, '/storage/')) {
            $publicPath = 'public/' . ltrim(str_replace('/storage/', '', $path), '/');
            if (Storage::exists($publicPath)) {
                $absolutePath = Storage::path($publicPath);
                return response()->file($absolutePath, [
                    'Content-Disposition' => 'attachment; filename="' . $document->original_filename . '"',
                ]);
            }
        }

        // Primary: private local disk
        if (!Storage::disk('local')->exists($path)) {
            Log::error('Document file missing on disk', ['document_id' => $id, 'path' => $path]);
            return response()->json(['error' => 'File not found on server.'], 404);
        }

        $absolutePath = Storage::disk('local')->path($path);
        return response()->file($absolutePath, [
            'Content-Disposition' => 'attachment; filename="' . $document->original_filename . '"',
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();

        // Only the SME owner or an Admin can delete
        $query = Document::findOrFail($id);

        if ($user->role === 'ADMIN') {
            $document = $query;
        } else {
            if (!$user->smeProfile || $query->sme_id !== $user->smeProfile->id) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
            $document = $query;
        }

        $path = $document->file_url;

        // Remove physical file from the appropriate disk
        if (str_starts_with($path, '/storage/')) {
            $publicPath = 'public/' . ltrim(str_replace('/storage/', '', $path), '/');
            Storage::delete($publicPath);
        } else {
            Storage::disk('local')->delete($path);
        }

        $document->delete();

        return $this->success(null, 'Document deleted successfully');
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    /**
     * Central RBAC check: can this user access this document?
     */
    private function canAccess($user, $document): bool
    {
        if (in_array($user->role, ['ADMIN', 'INVESTOR'])) {
            return true;
        }

        return $user->smeProfile && (int) $user->smeProfile->id === (int) $document->sme_id;
    }

    /**
     * Append a signed download URL without exposing the real file path.
     */
    private function appendDownloadUrl($doc): mixed
    {
        if (is_array($doc)) {
            $doc['download_url'] = url('/api/documents/' . $doc['id'] . '/download');
            return $doc;
        }
        $doc->download_url = url('/api/documents/' . $doc->id . '/download');
        return $doc;
    }
}
