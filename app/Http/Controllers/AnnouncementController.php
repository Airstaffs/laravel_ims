<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnnouncementController extends Controller
{
    // ── Admin list (/hr/announcements/admin) ──────────────────────
    public function adminIndex(Request $request): JsonResponse
    {
        $q = Announcement::query()->latest();

        if ($request->status === 'active') {
            $q->where('is_active', true);
        }
        if ($request->status === 'draft') {
            $q->where('is_active', false);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $q->where('type', $request->type);
        }

        if ($request->filled('q')) {
            $q->where(function ($sub) use ($request) {
                $sub->where('title', 'like', "%{$request->q}%")
                    ->orWhere('content', 'like', "%{$request->q}%");
            });
        }

        return response()->json($q->get());
    }

    // ── Save / update (/hr/announcements/save) ────────────────────
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'save_mode' => 'required|in:draft,active',
            'type' => 'nullable|in:manual,birthday,anniversary',
            'recipients' => 'nullable|array',
            'recipients.*' => 'integer',
        ]);

        $fields = [
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
            'is_active' => $data['save_mode'] === 'active',
            'type' => $data['type'] ?? 'manual',
            'recipients_json' => ! empty($data['recipients']) ? $data['recipients'] : null,
        ];

        if ($request->filled('id')) {
            $ann = Announcement::findOrFail($request->id);
            $ann->update($fields);
        } else {
            $fields['created_by'] = $request->user()?->username ?? 'admin';
            $fields['created_by_user_id'] = $request->user()?->id;
            $ann = Announcement::create($fields);
        }

        return response()->json(['success' => true, 'id' => $ann->id]);
    }

    // ── User dashboard check (/hr/dash/announcements) ─────────────
    public function dashIndex(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $userId = $request->user()->id;
        $username = $request->user()->username ?? $request->user()->name;

        $announcements = Announcement::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'content', 'start_at', 'end_at', 'readby', 'type', 'recipients_json']);

        $list = $announcements->filter(function ($ann) use ($userId, $username) {
            $recipients = $ann->recipients_json;
            if (! empty($recipients) && ! in_array($userId, $recipients)) {
                return false;
            }

            $readby = $ann->readby;
            if (! empty($readby) && in_array($username, $readby)) {
                return false;
            }

            return true;
        })->values();

        return response()->json($list->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'content' => $a->content,
            'start_at' => $a->start_at,
            'end_at' => $a->end_at,
            'readby' => $a->readby,
            'type' => $a->type,
        ]));
    }

    // ── Toggle active (/hr/announcements/toggle-active) ───────────
    public function toggleActive(Request $request): JsonResponse
    {
        $ann = Announcement::findOrFail($request->id);
        $ann->update(['is_active' => $request->boolean('make_active')]);

        return response()->json(['success' => true]);
    }

    // ── Acknowledge (/hr/dash/announcements/acknowledge) ──────────
    public function acknowledge(Request $request): JsonResponse
    {
        $request->validate(['announcement_id' => 'required|integer']);

        $ann = Announcement::findOrFail($request->announcement_id);
        $username = $request->input('username')
                 ?? $request->user()?->username
                 ?? $request->user()?->name;

        $readby = $ann->readby ?? [];
        if (! in_array($username, $readby)) {
            $readby[] = $username;
            $ann->update(['readby' => $readby]);
        }

        return response()->json(['success' => true]);
    }
}
