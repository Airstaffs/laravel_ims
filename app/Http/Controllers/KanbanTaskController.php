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

            // Upload images
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

            $mentions = $validated['mentions'] ?? [];
            $mentions = array_map('intval', $mentions);

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

            $userName = 'Unknown User';
            if (!empty($validated['user_id'])) {
                $user = DB::table('tbluser')->where('id', $validated['user_id'])->first();
                if ($user) {
                    $userName = $user->username;
                }
            }

            // Create activity log
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
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'removed_images' => 'nullable|array', // Array of filenames to remove
            ]);

            // Find the task
            $task = Task::findOrFail($validated['taskId']);

            // Get existing medias
            $existingMedias = $task->medias ? json_decode($task->medias, true) : [];

            // Handle removed images
            if (!empty($validated['removed_images'])) {
                $uploadDir = public_path('images/kanban_media');
                foreach ($validated['removed_images'] as $imageFilename) {
                    $imagePath = $uploadDir . '/' . $imageFilename;
                    if (File::exists($imagePath)) {
                        File::delete($imagePath);
                    }
                }
                // Remove from medias array
                $existingMedias = array_diff($existingMedias, $validated['removed_images']);
                $existingMedias = array_values($existingMedias); // Re-index array
            }

            // Handle new images upload
            if ($request->hasFile('images')) {
                $uploadDir = public_path('images/kanban_media');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                foreach ($request->file('images') as $image) {
                    $filename = time() . '_' . uniqid() . '_' . $image->getClientOriginalName();
                    $image->move($uploadDir, $filename);
                    $existingMedias[] = $filename;
                }
            }

            // Get current mentions
            $currentMentions = $task->mentions ? json_decode($task->mentions, true) : [];
            $newMentions = $validated['mentions'] ?? [];
            $newMentions = array_map('intval', $newMentions);

            // Find added and removed mentions
            $addedMentions = array_diff($newMentions, $currentMentions);
            $removedMentions = array_diff($currentMentions, $newMentions);

            // Add permissions for new mentions
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

            // Remove permissions for removed mentions
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

            // Get current user info for activity log
            $userName = 'Unknown User';
            if (!empty(auth()->id())) {
                $user = DB::table('tbluser')->where('id', auth()->id())->first();
                if ($user) {
                    $userName = $user->username;
                }
            }

            // Create activity log
            DB::table('tblkanbanactivitylog')->insert([
                'taskId' => $task->id,
                'userId' => auth()->id() ?? null,
                'description' => 'Task has been updated by ' . $userName . '.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Reload task to get latest data
            $task->refresh();
            
            // Decode medias and mentions for response
            $task->medias = $task->medias ? json_decode($task->medias, true) : [];
            $task->mentions = $task->mentions ? json_decode($task->mentions, true) : [];

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'task' => $task
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task update failed',
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