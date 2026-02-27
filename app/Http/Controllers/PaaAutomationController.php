<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaaAutomationController extends Controller
{
    public function fnskuSearch(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'],
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $store = $data['store'];
        $q = trim($data['q'] ?? '');
        $page = (int) ($data['page'] ?? 1);
        $pageSize = (int) ($data['pageSize'] ?? 20);

        $query = DB::table('tblfnsku')
            ->select('FNSKUID', 'FNSKU', 'MSKU', 'ASIN', 'Units', 'grading', 'storename', 'fnsku_status')
            ->where('storename', $store);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('MSKU', 'like', "%{$q}%")
                    ->orWhere('FNSKU', 'like', "%{$q}%")
                    ->orWhere('ASIN', 'like', "%{$q}%");
            });
        }

        // pagination (simple)
        $offset = ($page - 1) * $pageSize;

        $rows = $query
            ->orderByDesc('FNSKUID')
            ->offset($offset)
            ->limit($pageSize + 1)
            ->get();

        $hasMore = $rows->count() > $pageSize;
        if ($hasMore) {
            $rows = $rows->slice(0, $pageSize)->values();
        }

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'hasMore' => $hasMore,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'store' => ['required', 'string'],
            'marketplace_ids' => ['required', 'array', 'min:1'],
            'timezone' => ['required', 'string'],
            'time_local' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'frequency' => ['required', 'in:DAILY,ONCE'],
            'delta' => ['required', 'numeric'],
            'is_enabled' => ['required', 'in:0,1'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.msku' => ['required', 'string', 'max:100'],
        ]);

        $mskus = collect($data['items'])
            ->map(fn($x) => trim($x['msku']))
            ->filter()
            ->unique()
            ->values();

        return DB::transaction(function () use ($data, $mskus) {

            $id = $data['id'] ?? null;

            if ($id) {
                $exists = DB::table('tbl_paa_automations')
                    ->where('id', $id)
                    ->where('store', $data['store'])
                    ->exists();
                if (!$exists)
                    return response()->json(['ok' => false, 'message' => 'Not found'], 404);

                DB::table('tbl_paa_automations')
                    ->where('id', $id)
                    ->where('store', $data['store'])
                    ->update([
                        'store' => $data['store'],
                        'marketplace_ids' => json_encode($data['marketplace_ids']),
                        'timezone' => $data['timezone'],
                        'time_local' => $data['time_local'],
                        'frequency' => $data['frequency'],
                        'delta' => $data['delta'],
                        'is_enabled' => (int) $data['is_enabled'],
                        'updated_at' => now(),
                    ]);
            } else {
                $id = DB::table('tbl_paa_automations')->insertGetId([
                    'store' => $data['store'],
                    'marketplace_ids' => json_encode($data['marketplace_ids']),
                    'timezone' => $data['timezone'],
                    'time_local' => $data['time_local'],
                    'frequency' => $data['frequency'],
                    'delta' => $data['delta'],
                    'is_enabled' => (int) $data['is_enabled'],
                    'created_by' => session('user_name'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ---- SYNC ITEMS ----

            // 1) mark all existing as inactive first
            DB::table('tbl_paa_automation_items')
                ->where('automation_id', $id)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            // 2) upsert each MSKU as active=1 (insert new, or activate existing)
            foreach ($mskus as $msku) {
                // try update first
                $updated = DB::table('tbl_paa_automation_items')
                    ->where('automation_id', $id)
                    ->where('msku', $msku)
                    ->update(['is_active' => 1, 'updated_at' => now()]);

                if (!$updated) {
                    // insert new
                    DB::table('tbl_paa_automation_items')->insert([
                        'automation_id' => $id,
                        'msku' => $msku,
                        'sku' => null,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'ok' => true,
                'automation_id' => $id,
                'msku_count' => $mskus->count(),
            ]);
        });
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'],
        ]);

        $rows = DB::table('tbl_paa_automations')
            ->select('id', 'store', 'marketplace_ids', 'timezone', 'time_local', 'frequency', 'delta', 'is_enabled', 'next_run_at_utc', 'last_run_at_utc')
            ->where('store', $data['store'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function show($id)
    {
        $automation = DB::table('tbl_paa_automations')->where('id', $id)->first();
        if (!$automation)
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);

        $items = DB::table('tbl_paa_automation_items')
            ->select('id', 'automation_id', 'msku', 'sku', 'is_active', 'created_at', 'updated_at')
            ->where('automation_id', $id)
            ->orderBy('msku')
            ->get();

        return response()->json(['ok' => true, 'automation' => $automation, 'items' => $items]);
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $exists = DB::table('tbl_paa_automations')->where('id', $id)->exists();
            if (!$exists)
                return response()->json(['ok' => false, 'message' => 'Not found'], 404);

            DB::table('tbl_paa_automations')->where('id', $id)->delete(); // cascades to items/runs/run_items via FK
            return response()->json(['ok' => true]);
        });
    }
}