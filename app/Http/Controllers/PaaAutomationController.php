<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaaAutomationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'store' => ['nullable', 'string'],
        ]);

        $query = DB::table('tbl_paa_automations')
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
            ->orderByDesc('id');


        $rows = $query
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $rules = json_decode($row->rules_json ?: '[]', true) ?: [];

                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'store' => $row->store,
                    'marketplace_ids' => json_decode($row->marketplace_ids ?: '[]', true) ?: [],
                    'timezone' => $row->timezone,
                    'rules' => $rules,
                    'default_delta' => (float) ($row->default_delta ?? 0),
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
                'message' => 'Automation not found.',
            ], 404);
        }

        $items = DB::table('tbl_paa_automation_items')
            ->select(
                'id',
                'automation_id',
                'msku',
                'storename',
                'sku',
                'asin',
                'is_active',
                'created_at',
                'updated_at'
            )
            ->where('automation_id', $id)
            ->where('is_active', 1)
            ->orderBy('msku')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'automation_id' => (int) $item->automation_id,
                    'msku' => $item->msku,
                    'storename' => $item->storename,
                    'sku' => $item->sku,
                    'asin' => $item->asin,
                    'is_active' => (int) $item->is_active,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'automation' => [
                'id' => (int) $automation->id,
                'name' => $automation->name,
                'store' => $automation->store,
                'marketplace_ids' => json_decode($automation->marketplace_ids ?: '[]', true) ?: [],
                'timezone' => $automation->timezone,
                'rules' => json_decode($automation->rules_json ?: '[]', true) ?: [],
                'default_delta' => (float) ($automation->default_delta ?? 0),
                'is_enabled' => (int) $automation->is_enabled,
                'last_run_at_utc' => $automation->last_run_at_utc,
                'created_at' => $automation->created_at,
                'updated_at' => $automation->updated_at,
            ],
            'items' => $items,
        ]);
    }
    public function save(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:150'],
            'store' => ['required', 'string', 'max:100'],
            'marketplace_ids' => ['required', 'array', 'min:1'],
            'marketplace_ids.*' => ['required', 'string'],
            'timezone' => ['required', 'string', 'max:100'],
            'is_enabled' => ['required', 'in:0,1'],

            'rules' => ['required', 'array', 'min:1'],
            'rules.*.start' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'rules.*.end' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'rules.*.min' => ['required', 'numeric'],
            'rules.*.max' => ['required', 'numeric'],
            'rules.*.delta' => ['required', 'numeric'],

            'default_delta' => ['nullable', 'numeric'],
        ]);

        $rules = collect($data['rules'])
            ->map(function ($r) {
                return [
                    'start' => trim((string) ($r['start'] ?? '')),
                    'end' => trim((string) ($r['end'] ?? '')),
                    'min' => is_numeric($r['min'] ?? null) ? (float) $r['min'] : null,
                    'max' => is_numeric($r['max'] ?? null) ? (float) $r['max'] : null,
                    'delta' => is_numeric($r['delta'] ?? null) ? (float) $r['delta'] : null,
                ];
            })
            ->values()
            ->all();

        foreach ($rules as $rule) {
            if ($rule['start'] === '' || $rule['end'] === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Each rule must include a start and end time.',
                ], 422);
            }

            if ($rule['start'] === $rule['end']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Rule start and end time cannot be the same.',
                ], 422);
            }

            if ($rule['min'] === null || $rule['max'] === null || $rule['delta'] === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Each rule must include min, max, and delta.',
                ], 422);
            }

            if ($rule['min'] >= $rule['max']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Each rule must satisfy min < max.',
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

        return DB::transaction(function () use ($data, $rules, $defaultDelta) {
            $id = $data['id'] ?? null;

            $payload = [
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
                    ->exists();

                if (!$exists) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Automation not found.',
                    ], 404);
                }

                DB::table('tbl_paa_automations')
                    ->where('id', $id)
                    ->update(array_merge($payload, [
                        'last_run_at_utc' => null,
                    ]));
            } else {
                $payload['created_by'] = session('user_name');
                $payload['created_at'] = now();

                $id = DB::table('tbl_paa_automations')->insertGetId($payload);
            }

            return response()->json([
                'ok' => true,
                'automation_id' => $id,
                'rules' => $rules,
                'default_delta' => $defaultDelta,
            ]);
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $exists = DB::table('tbl_paa_automations')
                ->where('id', $id)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Automation not found.',
                ], 404);
            }

            DB::table('tbl_paa_automation_items')
                ->where('automation_id', $id)
                ->delete();

            DB::table('tbl_paa_automations')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Automation deleted successfully.',
            ]);
        });
    }

    public function assignItems(Request $request)
    {
        $data = $request->validate([
            'automation_id' => ['required', 'integer'],
            'store' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.msku' => ['required', 'string', 'max:100'],
            'items.*.storename' => ['required', 'string', 'max:100'],
            'items.*.asin' => ['nullable', 'string', 'max:50'],
        ]);

        $automation = DB::table('tbl_paa_automations')
            ->where('id', $data['automation_id'])
            ->first();

        if (!$automation) {
            return response()->json([
                'ok' => false,
                'message' => 'Selected automation was not found for this store.',
            ], 404);
        }

        $items = collect($data['items'])
            ->map(function ($item) {
                return [
                    'msku' => trim((string) ($item['msku'] ?? '')),
                    'storename' => trim((string) ($item['storename'] ?? '')),
                    'asin' => trim((string) ($item['asin'] ?? '')) ?: null,
                ];
            })
            ->filter(fn($item) => $item['msku'] !== '' && $item['storename'] !== '')
            ->unique(fn($item) => $item['msku'] . '||' . $item['storename'])
            ->values();

        if ($items->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No valid items were provided.',
            ], 422);
        }

        $inserted = 0;
        $reactivated = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $items, &$inserted, &$reactivated, &$skipped) {
            foreach ($items as $item) {
                $existing = DB::table('tbl_paa_automation_items')
                    ->where('automation_id', $data['automation_id'])
                    ->where('msku', $item['msku'])
                    ->where('storename', $item['storename'])
                    ->first();

                if ($existing) {
                    $updatePayload = [
                        'asin' => $item['asin'],
                        'updated_at' => now(),
                    ];

                    if ((int) $existing->is_active !== 1) {
                        $updatePayload['is_active'] = 1;
                        $reactivated++;
                    } else {
                        $skipped++;
                    }

                    DB::table('tbl_paa_automation_items')
                        ->where('id', $existing->id)
                        ->update($updatePayload);

                    continue;
                }

                DB::table('tbl_paa_automation_items')->insert([
                    'automation_id' => $data['automation_id'],
                    'msku' => $item['msku'],
                    'storename' => $item['storename'],
                    'sku' => null,
                    'asin' => $item['asin'],
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inserted++;
            }
        });

        $assignedItems = DB::table('tbl_paa_automation_items')
            ->select('id', 'automation_id', 'msku', 'storename', 'sku', 'asin', 'is_active', 'created_at', 'updated_at')
            ->where('automation_id', $data['automation_id'])
            ->where('is_active', 1)
            ->orderBy('msku')
            ->get();

        return response()->json([
            'ok' => true,
            'message' => 'Selected listings assigned successfully.',
            'automation_id' => (int) $data['automation_id'],
            'inserted' => $inserted,
            'reactivated' => $reactivated,
            'skipped' => $skipped,
            'items' => $assignedItems,
        ]);
    }

    public function removeItem($id)
    {
        $item = DB::table('tbl_paa_automation_items')
            ->where('id', $id)
            ->first();

        if (!$item) {
            return response()->json([
                'ok' => false,
                'message' => 'Assigned item not found.',
            ], 404);
        }

        DB::table('tbl_paa_automation_items')
            ->where('id', $id)
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'message' => 'Assigned MSKU removed successfully.',
            'id' => (int) $id,
        ]);
    }

    public function bulkRemoveItems(Request $request)
    {
        $data = $request->validate([
            'automation_id' => ['required', 'integer'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer'],
        ]);

        $updated = DB::table('tbl_paa_automation_items')
            ->where('automation_id', $data['automation_id'])
            ->whereIn('id', $data['item_ids'])
            ->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'message' => 'Selected assigned MSKUs removed successfully.',
            'updated' => $updated,
        ]);
    }
}