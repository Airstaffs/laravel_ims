<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class KanbanTaskController extends Controller
{
 public function addTask(Request $request)
{
    try {
        // Validate input
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'note' => 'nullable|string',
            'status' => 'required|string',
            'priority' => 'required|string',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'user_id' => 'nullable|integer',
        ]);

        // Handle image uploads
        $mediaPaths = [];
        if ($request->hasFile('images')) {
            $uploadDir = public_path('images/kanban_media');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            foreach ($request->file('images') as $image) {
                $filename = time() . '_' . $image->getClientOriginalName();
                $image->move($uploadDir, $filename);
                $mediaPaths[] = $filename;
            }
        }

        // Prepare mentions array
        $mentions = $validated['mentions'] ?? [];
        $mentions = array_map('intval', $mentions);

        // Create task
        $task = Task::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'note' => $validated['note'] ?? '',
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'mentions' => json_encode($mentions),
            'medias' => json_encode($mediaPaths),
            'userId' => $validated['user_id'] ?? null,
        ]);

        // Add entries to user permission table
        foreach ($mentions as $userId) {
            DB::table('tblkanbanuserpermission')->insert([
                'taskId' => $task->id,
                'userId' => $userId,
                'can_edit' => false,
                'can_comment' => true,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'task' => $task
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Task creation failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getTasks(Request $request)
{
    $validated = $request->validate([
        'userId' => 'required|integer',
    ]);

    $userId = $validated['userId'];

    try {
        $tasks = Task::where('userId', $userId)
    ->orWhere(function ($query) use ($userId) {
        $query->whereNotNull('mentions')
              ->where(function ($q) use ($userId) {
                  $q->where('mentions', 'like', '%[' . $userId . ']%')
                    ->orWhere('mentions', 'like', '%,' . $userId . ',%')
                    ->orWhere('mentions', 'like', '%,' . $userId . ']%')
                    ->orWhere('mentions', 'like', '%[' . $userId . ',%');
              });
    })
    ->orderBy('created_at', 'desc')
    ->get();
        $tasks->transform(function ($task) {
            // Decode JSON columns safely
            $task->mentions = $task->mentions ? json_decode($task->mentions, true) : [];
            $task->medias = $task->medias ? json_decode($task->medias, true) : [];

            if (!empty($task->mentions)) {
                // Fetch user info along with permissions for this task
                $users = DB::table('tbluser as u')
                    ->leftJoin('tblkanbanuserpermission as p', function ($join) use ($task) {
                        $join->on('u.id', '=', 'p.userId')
                             ->where('p.taskId', $task->id);
                    })
                    ->select(
                        'u.id',
                        'u.username',
                        'u.profile_picture',
                        DB::raw('IFNULL(p.can_edit, 0) as can_edit'),
                        DB::raw('IFNULL(p.can_comment, 0) as can_comment'),
                        DB::raw('IFNULL(p.can_delete, 0) as can_delete')
                    )
                    ->whereIn('u.id', $task->mentions)
                    ->get();

                $task->mentions = $users;
            }

            return $task;
        });

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch tasks',
            'error' => $e->getMessage(),
        ], 500);
    }
}




public function deleteTask(Request $request)
{
    // Validate request inputs
    $validated = $request->validate([
        'taskId' => 'required|integer'
    ]);

    // Find the task by ID
    $task = Task::find($validated['taskId']);

    if (!$task) {
        return response()->json([
            'success' => false,
            'message' => 'Task not found.'
        ], 404);
    }

    // Decode the JSON array of images
    $images = json_decode($task->images, true);

    if (is_array($images)) {
        foreach ($images as $image) {
            $imagePath = public_path('images/kanban_media/'. $image);

            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }
    }

    // Delete the task record
    $task->delete();

    return response()->json([
        'sucess' => true,
        'message' => 'Task and associated images deleted successfully.'
    ], 200);
}




}
