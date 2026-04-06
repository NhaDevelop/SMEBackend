<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramComment;
use Illuminate\Http\Request;

class ProgramCommentController extends Controller
{
    /**
     * GET /api/programs/{id}/comments
     * List all comments for a specific program (forum thread).
     */
    public function index($programId)
    {
        $program = Program::findOrFail($programId);

        $comments = ProgramComment::with(['user:id,full_name,role'])
            ->where('program_id', $program->id)
            ->latest()
            ->get();

        return $this->success($comments, 'Program comments retrieved successfully');
    }
    /**
     * POST /api/programs/{id}/comments
     * Post a new comment/discussion message in a program's forum.
     */
    public function store(Request $request, $programId)
    {
        $program = Program::findOrFail($programId);

        $validated = $request->validate([
            'content' => 'required|string|max:5000'
        ]);

        $comment = ProgramComment::create([
            'program_id' => $program->id,
            'user_id' => auth()->id(),
            'content' => $validated['content']
        ]);

        return $this->success(
            $comment->load('user:id,full_name,role'),
            'Comment posted successfully',
            201
        );
    }

    /**
     * DELETE /api/programs/{programId}/comments/{commentId}
     * Delete your own comment.
     */
    public function destroy($programId, $commentId)
    {
        $comment = ProgramComment::where('program_id', $programId)
            ->where('user_id', auth()->id())
            ->findOrFail($commentId);

        $comment->delete();

        return $this->success(null, 'Comment deleted successfully');
    }
}