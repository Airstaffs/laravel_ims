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
            'link_data' => $request->input('link_data')
                ? json_encode($request->input('link_data'))
                : null,
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
            ->select(
                'nu.id as notif_user_id',
                'nu.read_status',
                'nu.created_at as user_created_at',
                'n.id as notif_id',
                'n.module',
                'n.title',
                'n.subtitle',
                'n.content',
                'n.severity',
                'n.link_data',
                'n.created_at as notif_created_at'
            )
            ->get();

        return response()->json($notifications);
    }

    // 3. Mark a notification as read
    public function markAsRead(Request $request)
    {
        DB::table('tblnotificationsuser')
            ->where('notif_id', $request->input('notif_id'))
            ->where('userid', $request->input('user_id'))
            ->update([
                'read_status' => 'read',
                'read_at' => now()
            ]);

        return response()->json(['success' => true]);
    }

    // 4. Mark a notification as unread
    public function markAsUnread(Request $request)
    {
        DB::table('tblnotificationsuser')
            ->where('notif_id', $request->input('notif_id'))
            ->where('userid', $request->input('user_id'))
            ->update([
                'read_status' => 'unread',
                'read_at' => null
            ]);

        return response()->json(['success' => true]);
    }

    public function getUnreadCount($userId)
    {
        $count = DB::table('tblnotificationsuser')
            ->where('userid', $userId)
            ->where('read_status', 'unread')
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
