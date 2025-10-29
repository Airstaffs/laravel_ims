<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KanbanCommentController extends Controller
{
    //

  public function getTaskComments(Request $request)
{
    $validated = $request->validate([
        'taskId' => ['required', 'integer'],
    ]);

    $comments = DB::table('tblkanbancomments as c')
        ->join('tbluser as u', 'c.userId', '=', 'u.id')
        ->select(
            'c.id',
            'c.userId',
            'u.username',
            'u.profile_picture',
            'c.content',
            'c.medias',
            'c.files',
            'c.created_at'
        )
        ->where('c.taskId', $validated['taskId'])
        ->orderBy('c.created_at', 'asc')
        ->get()
        ->map(function ($comment) {
            $comment->medias = json_decode($comment->medias, true);
            $comment->files = json_decode($comment->files, true);
            return $comment;
        });

    return response()->json([
        'success' => true,
        'comments' => $comments,
    ]);
}

public function addTaskComment(Request $request)
{
    // ✅ Validate input
    $validated = $request->validate([
        'taskId' => 'required|integer',
        'userId' => 'required|integer',
        'content' => 'required|string|max:1000',
        // 'medias' => 'nullable|array',
        // 'files' => 'nullable|array',
    ]);

    // ✅ Insert comment
    $commentId = DB::table('tblkanbancomments')->insertGetId([
        'taskId' => $validated['taskId'],
        'userId' => $validated['userId'],
        'content' => $validated['content'],
        'medias' => json_encode($validated['medias'] ?? []),
        'files' => json_encode($validated['files'] ?? []),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // ✅ Fetch the newly added comment with user info
    $comment = DB::table('tblkanbancomments as c')
        ->join('tbluser as u', 'u.id', '=', 'c.userId')
        ->select(
            'c.id',
            'c.taskId',
            'c.userId',
            'u.username',
            'u.profile_picture',
            'c.content',
            'c.medias',
            'c.files',
            'c.created_at'
        )
        ->where('c.id', $commentId)
        ->first();

    return response()->json([
        'success' => true,
        'message' => 'Comment added successfully!',
        'comment' => $comment
    ]);
}


}
