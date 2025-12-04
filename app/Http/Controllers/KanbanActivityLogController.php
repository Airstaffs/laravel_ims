<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KanbanActivityLogController extends Controller
{

    public function getActivityLogs(Request $request)
    {
        try {
            $validated = $request->validate([
                'taskId' => 'required|integer|exists:tblkanbantasks,id',
            ]);

            $logs = DB::table('tblkanbanactivitylog')
                ->join('tbluser', 'tblkanbanactivitylog.userId', '=', 'tbluser.id')
                ->where('tblkanbanactivitylog.taskId', $validated['taskId'])
                ->select(
                    'tblkanbanactivitylog.id',
                    'tblkanbanactivitylog.description',
                    'tblkanbanactivitylog.created_at',
                    'tbluser.username as username',
                    'tbluser.profile_picture as profile_picture'
                )
                ->orderBy('tblkanbanactivitylog.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'logs' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activity logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
