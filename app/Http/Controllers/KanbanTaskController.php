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
        // ✅ Validate request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'note' => 'nullable|string',
            'status' => 'required|string',
            'priority' => 'required|string',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer',
            'user_id' => 'nullable|integer',

            // Accept both images and documents
            'files.*' => 'file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt|max:5120',
        ]);

        $mediaPaths = [];

        // ✅ Check for files
        if ($request->hasFile('files')) {
            $baseDir = public_path('images/kanban_media');
            $imageDir = $baseDir . '/images';
            $fileDir  = $baseDir . '/files';

            // Make sure directories exist
            foreach ([$imageDir, $fileDir] as $dir) {
                if (!file_exists($dir)) {
                    mkdir($dir, 0775, true);
                }
            }

            foreach ($request->file('files') as $file) {
                $originalName = preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $filename = time() . '_' . $originalName;

                // ✅ Detect file type (image vs document)
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $file->move($imageDir, $filename);
                    $mediaPaths[] = 'images/' . $filename;
                } else {
                    $file->move($fileDir, $filename);
                    $mediaPaths[] = 'files/' . $filename;
                }
            }
        }

        // ✅ Mentions cleanup
        $mentions = $validated['mentions'] ?? [];
        $mentions = array_map('intval', $mentions);

        // ✅ Create task record
        $task = Task::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'note' => $validated['note'] ?? '',
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'mentions' => json_encode($mentions),
            'medias' => json_encode($mediaPaths), // contains paths like 'images/filename.jpg' or 'files/filename.pdf'
            'userId' => $validated['user_id'] ?? null,
        ]);

        // ✅ Assign mention permissions
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

        // ✅ Activity log
        $userName = 'Unknown User';
        if (!empty($validated['user_id'])) {
            $user = DB::table('tbluser')->where('id', $validated['user_id'])->first();
            if ($user) {
                $userName = $user->username;
            }
        }

        DB::table('tblkanbanactivitylog')->insert([
            'taskId' => $task->id,
            'userId' => $validated['user_id'] ?? null,
            'description' => 'Task has been created by ' . $userName . '.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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


   public function editTask(Request $request)
{
    try {
        $validated = $request->validate([
            'taskId' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'note' => 'nullable|string',
            'status' => 'required|string',
            'priority' => 'required|string',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer',
            'images.*' => 'file|max:5120', // accepts any file, max 5MB
            'removed_images' => 'nullable|array',
        ]);

        $task = Task::findOrFail($validated['taskId']);

        $existingMedias = $task->medias ? json_decode($task->medias, true) : [];

        // Remove selected media files
      // Remove selected media files
if (!empty($validated['removed_images'])) {
    foreach ($validated['removed_images'] as $relativePath) {
        $fullPath = public_path("images/kanban_media/{$relativePath}");
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }

    // Remove from the JSON list
    $existingMedias = array_values(array_diff($existingMedias, $validated['removed_images']));
}

// Upload new files
if ($request->hasFile('images')) {
    $uploadDirImages = public_path('images/kanban_media/images');
    $uploadDirFiles = public_path('images/kanban_media/files');

    if (!file_exists($uploadDirImages)) mkdir($uploadDirImages, 0775, true);
    if (!file_exists($uploadDirFiles)) mkdir($uploadDirFiles, 0775, true);

    foreach ($request->file('images') as $file) {
        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

        if (str_starts_with($file->getMimeType(), 'image/')) {
            $file->move($uploadDirImages, $filename);
            $existingMedias[] = "images/{$filename}";
        } else {
            $file->move($uploadDirFiles, $filename);
            $existingMedias[] = "files/{$filename}";
        }
    }
}



        // Mentions handling
        $currentMentions = $task->mentions ? json_decode($task->mentions, true) : [];
        $newMentions = $validated['mentions'] ?? [];
        $newMentions = array_map('intval', $newMentions);

        $addedMentions = array_diff($newMentions, $currentMentions);
        $removedMentions = array_diff($currentMentions, $newMentions);

        foreach ($addedMentions as $userId) {
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

        if (!empty($removedMentions)) {
            DB::table('tblkanbanuserpermission')
                ->where('taskId', $task->id)
                ->whereIn('userId', $removedMentions)
                ->delete();
        }

        // Update task
        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? $task->description,
            'note' => $validated['note'] ?? $task->note,
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'mentions' => json_encode($newMentions),
            'medias' => json_encode($existingMedias),
        ]);

        // Activity log
        $userName = auth()->user()->username ?? 'Unknown User';
        DB::table('tblkanbanactivitylog')->insert([
            'taskId' => $task->id,
            'userId' => auth()->id(),
            'description' => "Task has been updated by {$userName}.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $task->refresh();
        $task->medias = $task->medias ? json_decode($task->medias, true) : [];
        $task->mentions = $task->mentions ? json_decode($task->mentions, true) : [];

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'task' => $task,
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Task update failed',
            'error' => $e->getMessage(),
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
        $images = json_decode($task->medias, true);

        if (is_array($images)) {
            $uploadDir = public_path('images/kanban_media');
            foreach ($images as $image) {
                $imagePath = $uploadDir . '/' . $image;

                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
        }

        // Delete related permissions
        DB::table('tblkanbanuserpermission')->where('taskId', $task->id)->delete();

        // Delete the task record
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task and associated images deleted successfully.'
        ], 200);
    }
}