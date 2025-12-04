<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KanbanUserPermissionController extends Controller
{
     public function getUserPermissions(Request $request)
{
    $validated = $request->validate([
        'taskId' => 'required|integer',
    ]);

    $permissions = DB::table('tblkanbanuserpermission as p')
        ->join('tbluser as u', 'p.userId', '=', 'u.id')
        ->where('p.taskId', $validated['taskId'])
        ->select(
            'p.id',
            'p.taskId',
            'p.userId',
            'u.username',
            'p.can_edit',
            'p.can_comment',
            'p.can_delete',
            'p.created_at',
            'p.updated_at'
        )
        ->get();

    return response()->json([
        'success' => true,
        'permissions' => $permissions
    ]);
}

public function saveUserPermissions(Request $request)
{
    // Validate input
    $validated = $request->validate([
        'taskId' => 'required|integer',
        'permissions' => 'required|array',
        'permissions.*.userId' => 'required|integer',
        'permissions.*.can_edit' => 'required|integer|in:0,1',
        'permissions.*.can_comment' => 'required|integer|in:0,1',
        'permissions.*.can_delete' => 'required|integer|in:0,1',
    ]);

    try {
        foreach ($validated['permissions'] as $perm) {
            DB::table('tblkanbanuserpermission')
                ->where('taskId', $validated['taskId'])
                ->where('userId', $perm['userId'])
                ->update([
                    'can_edit' => $perm['can_edit'],
                    'can_comment' => $perm['can_comment'],
                    'can_delete' => $perm['can_delete'],
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully.',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update permissions.',
            'error' => $e->getMessage()
        ], 500);
    }
}


}
