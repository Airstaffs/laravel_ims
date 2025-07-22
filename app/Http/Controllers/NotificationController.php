<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Services\UserLogService;

class NotificationController extends Controller
{
    // 1. Create notification and assign to users
    public function create(Request $request)
    {
        $notificationId = DB::table('tblnotifications')->insertGetId([
            'module' => $request->input('module'),
            'title' => $request->input('title'),
            'subtitle' => $request->input('subtitle'),
            'content' => $request->input('content'),
            'severity' => $request->input('severity', 'info'),
            'action_made' => $request->input('action_made'),
            'created_at' => now()
        ]);

        $userIds = $request->input('user_ids', []);

        foreach ($userIds as $userId) {
            DB::table('tblnotificationsuser')->insert([
                'notif_id' => $notificationId,
                'userid' => $userId,
                'read_status' => 'unread',
                'created_at' => now()
            ]);
        }

        return response()->json(['success' => true, 'notif_id' => $notificationId]);
    }

    // 2. Fetch notifications for a specific user
    public function getByUser($userId)
    {
        $notifications = DB::table('tblnotificationsuser as nu')
            ->join('tblnotifications as n', 'n.id', '=', 'nu.notif_id')
            ->where('nu.userid', $userId)
            ->orderBy('n.created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    // 3. Mark a notification as read
    public function markAsRead(Request $request)
    {
        DB::table('tblnotificationsuser')
            ->where('notif_id', $request->input('notif_id'))
            ->where('userid', $request->input('user_id'))
            ->update(['read_status' => 'read']);

        return response()->json(['success' => true]);
    }

    // 4. Mark a notification as unread
    public function markAsUnread(Request $request)
    {
        DB::table('tblnotificationsuser')
            ->where('notif_id', $request->input('notif_id'))
            ->where('userid', $request->input('user_id'))
            ->update(['read_status' => 'unread']);

        return response()->json(['success' => true]);
    }
}
