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
            'name' => ['nullable', 'string', 'max:150'],
            'store' => ['required', 'string'],
            'marketplace_ids' => ['required', 'array', 'min:1'],
            'marketplace_ids.*' => ['string'],
            'timezone' => ['required', 'string'],
            'is_enabled' => ['required', 'in:0,1'],

            'rules' => ['required', 'array', 'min:1'],
            'rules.*.start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'rules.*.end' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'rules.*.min' => ['required', 'numeric'],
            'rules.*.max' => ['required', 'numeric'],
            'rules.*.delta' => ['required', 'numeric'],

            'default_delta' => ['nullable', 'numeric'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.msku' => ['required', 'string', 'max:100'],
        ]);

        $rules = collect($data['rules'])
            ->map(function ($r) {
                $start = trim((string) ($r['start'] ?? ''));
                $end = trim((string) ($r['end'] ?? ''));
                $min = is_numeric($r['min'] ?? null) ? (float) $r['min'] : null;
                $max = is_numeric($r['max'] ?? null) ? (float) $r['max'] : null;
                $delta = is_numeric($r['delta'] ?? null) ? (float) $r['delta'] : null;

                return [
                    'start' => $start,
                    'end' => $end,
                    'min' => $min,
                    'max' => $max,
                    'delta' => $delta,
                ];
            })
            ->values()
            ->all();

        foreach ($rules as $r) {
            if ($r['start'] === '' || $r['end'] === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Each rule must include start and end time',
                ], 422);
            }

            if ($r['start'] === $r['end']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Rule start and end time cannot be the same',
                ], 422);
            }

            if ($r['min'] === null || $r['max'] === null || $r['delta'] === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Rules must include start/end/min/max/delta',
                ], 422);
            }

            if (!($r['min'] < $r['max'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Each rule must satisfy min < max',
                ], 422);
            }
        }

        usort($rules, function ($a, $b) {
            if ($a['start'] === $b['start']) {
                return $a['min'] <=> $b['min'];
            }
            return strcmp($a['start'], $b['start']);
        });

        $defaultDelta = is_numeric($data['default_delta'] ?? null)
            ? (float) $data['default_delta']
            : 0.0;

        $mskus = collect($data['items'])
            ->map(fn($x) => trim($x['msku']))
            ->filter()
            ->unique()
            ->values();

        return DB::transaction(function () use ($data, $rules, $defaultDelta, $mskus) {
            $id = $data['id'] ?? null;

            $payloadUpdate = [
                'name' => $data['name'] ?? null,
                'store' => $data['store'],
                'marketplace_ids' => json_encode(array_values($data['marketplace_ids'])),
                'timezone' => $data['timezone'],
                'rules_json' => json_encode($rules),
                'default_delta' => $defaultDelta,
                'is_enabled' => (int) $data['is_enabled'],
                'updated_at' => now(),
            ];

            if ($id) {
                $exists = DB::table('tbl_paa_automations')
                    ->where('id', $id)
                    ->where('store', $data['store'])
                    ->exists();

                if (!$exists) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Not found',
                    ], 404);
                }

                DB::table('tbl_paa_automations')
                    ->where('id', $id)
                    ->where('store', $data['store'])
                    ->update($payloadUpdate);

                // Optional reset so edited automation is treated as fresh next cron cycle
                DB::table('tbl_paa_automations')
                    ->where('id', $id)
                    ->update([
                        'last_run_at_utc' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                $payloadInsert = $payloadUpdate;
                $payloadInsert['created_by'] = session('user_name');
                $payloadInsert['created_at'] = now();

                $id = DB::table('tbl_paa_automations')->insertGetId($payloadInsert);
            }

            DB::table('tbl_paa_automation_items')
                ->where('automation_id', $id)
                ->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);

            foreach ($mskus as $msku) {
                $updated = DB::table('tbl_paa_automation_items')
                    ->where('automation_id', $id)
                    ->where('msku', $msku)
                    ->update([
                        'is_active' => 1,
                        'updated_at' => now(),
                    ]);

                if (!$updated) {
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
                'rules' => $rules,
                'default_delta' => $defaultDelta,
            ]);
        });
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'store' => ['required', 'string'],
        ]);

        $rows = DB::table('tbl_paa_automations')
            ->select(
                'id',
                'name',
                'store',
                'marketplace_ids',
                'timezone',
                'rules_json',
                'default_delta',
                'is_enabled',
                'last_run_at_utc',
                'created_at',
                'updated_at'
            )
            ->where('store', $data['store'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $rules = json_decode($row->rules_json ?: '[]', true) ?: [];

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'store' => $row->store,
                    'marketplace_ids' => json_decode($row->marketplace_ids ?: '[]', true) ?: [],
                    'timezone' => $row->timezone,
                    'rules' => $rules,
                    'default_delta' => $row->default_delta,
                    'is_enabled' => (int) $row->is_enabled,
                    'last_run_at_utc' => $row->last_run_at_utc,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'rule_count' => count($rules),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'rows' => $rows,
        ]);
    }

    public function show($id)
    {
        $automation = DB::table('tbl_paa_automations')
            ->where('id', $id)
            ->first();

        if (!$automation) {
            return response()->json([
                'ok' => false,
                'message' => 'Not found',
            ], 404);
        }

        $items = DB::table('tbl_paa_automation_items')
            ->select('id', 'automation_id', 'msku', 'sku', 'is_active', 'created_at', 'updated_at')
            ->where('automation_id', $id)
            ->orderBy('msku')
            ->get();

        return response()->json([
            'ok' => true,
            'automation' => [
                'id' => $automation->id,
                'name' => $automation->name,
                'store' => $automation->store,
                'marketplace_ids' => json_decode($automation->marketplace_ids ?: '[]', true) ?: [],
                'timezone' => $automation->timezone,
                'rules' => json_decode($automation->rules_json ?: '[]', true) ?: [],
                'default_delta' => $automation->default_delta,
                'is_enabled' => (int) $automation->is_enabled,
                'last_run_at_utc' => $automation->last_run_at_utc,
                'created_at' => $automation->created_at,
                'updated_at' => $automation->updated_at,
            ],
            'items' => $items,
        ]);
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $exists = DB::table('tbl_paa_automations')->where('id', $id)->exists();

            if (!$exists) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Not found',
                ], 404);
            }

            DB::table('tbl_paa_automations')->where('id', $id)->delete();

            return response()->json([
                'ok' => true,
            ]);
        });
    }
}