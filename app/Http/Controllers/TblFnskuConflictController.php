<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class TblFnskuConflictController extends Controller
{
    public function apply(Request $request)
    {
        $data = $request->validate([
            'conflict_id' => ['required', 'integer', 'min:1'],
            'msku' => ['nullable', 'string'],
            'store' => ['nullable', 'string'],
        ]);

        $resolvedBy = Auth::id() ?? 0;

        try {
            $result = DB::transaction(function () use ($data, $resolvedBy) {
                $conflict = DB::table('tblfnskuconflicts')
                    ->where('id', $data['conflict_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$conflict) {
                    throw new \Exception('Conflict record not found.');
                }

                if (($conflict->status ?? '') !== 'pending') {
                    throw new \Exception('Conflict record is not pending.');
                }

                $rowId = (int) ($conflict->FNSKUID ?? 0);
                $msku = trim((string) ($conflict->MSKU ?? ''));
                $store = trim((string) ($conflict->storename ?? ''));
                $newFnsku = trim((string) ($conflict->newfnsku ?? ''));
                $newGrading = trim((string) ($conflict->newgrading ?? ''));

                if ($msku === '') {
                    throw new \Exception('MSKU is missing from conflict record.');
                }

                if ($newFnsku === '') {
                    throw new \Exception('New FNSKU is missing from conflict record.');
                }

                // 1) Update tblfnsku
                if ($rowId > 0) {
                    DB::table('tblfnsku')
                        ->where('FNSKUID', $rowId)
                        ->update([
                            'FNSKU' => $newFnsku,
                            'grading' => $newGrading !== '' ? $newGrading : DB::raw('grading'),
                            'fnsku_update_conflict' => 0,
                            'fnsku_conflict_last_notified_at' => null,
                        ]);
                } else {
                    DB::table('tblfnsku')
                        ->where('MSKU', $msku)
                        ->where('storename', $store)
                        ->update([
                            'FNSKU' => $newFnsku,
                            'grading' => $newGrading !== '' ? $newGrading : DB::raw('grading'),
                            'fnsku_update_conflict' => 0,
                            'fnsku_conflict_last_notified_at' => null,
                        ]);
                }

                // 2) Update tblproduct and preserve prefix
                $tblproductUpdated = $this->updateTblproductFnskuViewerByMskuPreservePrefix($msku, $newFnsku);

                // 3) Mark conflict as applied
                DB::table('tblfnskuconflicts')
                    ->where('id', $data['conflict_id'])
                    ->update([
                        'status' => 'applied',
                        'resolved_at' => now(),
                        'resolved_by' => $resolvedBy,
                        'updated_at' => now(),
                    ]);

                return [
                    'conflict_id' => $data['conflict_id'],
                    'status' => 'applied',
                    'msku' => $msku,
                    'newfnsku' => $newFnsku,
                    'tblproduct_updated' => $tblproductUpdated,
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => 'Pending FNSKU applied successfully.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function override(Request $request)
    {
        $data = $request->validate([
            'conflict_id' => ['required', 'integer', 'min:1'],
            'msku' => ['nullable', 'string'],
            'store' => ['nullable', 'string'],
        ]);

        $resolvedBy = Auth::id() ?? 0;

        try {
            $result = DB::transaction(function () use ($data, $resolvedBy) {
                $conflict = DB::table('tblfnskuconflicts')
                    ->where('id', $data['conflict_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$conflict) {
                    throw new \Exception('Conflict record not found.');
                }

                if (($conflict->status ?? '') !== 'pending') {
                    throw new \Exception('Conflict record is not pending.');
                }

                $rowId = (int) ($conflict->FNSKUID ?? 0);
                $msku = trim((string) ($conflict->MSKU ?? ''));
                $store = trim((string) ($conflict->storename ?? ''));

                if ($msku === '') {
                    throw new \Exception('MSKU is missing from conflict record.');
                }

                // Clear the block only
                if ($rowId > 0) {
                    DB::table('tblfnsku')
                        ->where('FNSKUID', $rowId)
                        ->update([
                            'fnsku_update_conflict' => 0,
                            'fnsku_conflict_last_notified_at' => null,
                        ]);
                } else {
                    DB::table('tblfnsku')
                        ->where('MSKU', $msku)
                        ->where('storename', $store)
                        ->update([
                            'fnsku_update_conflict' => 0,
                            'fnsku_conflict_last_notified_at' => null,
                        ]);
                }

                DB::table('tblfnskuconflicts')
                    ->where('id', $data['conflict_id'])
                    ->update([
                        'status' => 'kept_current',
                        'resolved_at' => now(),
                        'resolved_by' => $resolvedBy,
                        'updated_at' => now(),
                    ]);

                return [
                    'conflict_id' => $data['conflict_id'],
                    'status' => 'kept_current',
                    'msku' => $msku,
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => 'Conflict cleared. Current FNSKU kept.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function updateTblproductFnskuViewerByMskuPreservePrefix(string $msku, string $newBaseFnsku): int
    {
        $newBaseFnsku = trim($newBaseFnsku);
        if ($newBaseFnsku === '') {
            return 0;
        }

        $rows = DB::table('tblproduct')
            ->select('ProductID', 'FNSKUviewer')
            ->where('MSKUviewer', $msku)
            ->get();

        $updated = 0;

        foreach ($rows as $row) {
            $current = trim((string) ($row->FNSKUviewer ?? ''));

            $newValue = $this->rebuildFnskuWithExistingPrefix($current, $newBaseFnsku);

            $currentBase = $this->extractBaseFnsku($current);

            if ($current !== '' && $currentBase === $newBaseFnsku) {
                continue;
            }

            DB::table('tblproduct')
                ->where('ProductID', $row->ProductID)
                ->update([
                    'FNSKUviewer' => $newValue,
                ]);

            $updated++;
        }

        return $updated;
    }

    private function extractBaseFnsku(?string $fnsku): string
    {
        $fnsku = trim((string) $fnsku);

        if ($fnsku === '') {
            return '';
        }

        if (preg_match('/^([A-Z])(\d+)(X.+)$/', $fnsku, $m)) {
            return $m[3];
        }

        return $fnsku;
    }

    private function rebuildFnskuWithExistingPrefix(?string $currentValue, string $newBaseFnsku): string
    {
        $currentValue = trim((string) $currentValue);
        $newBaseFnsku = trim($newBaseFnsku);

        if ($currentValue !== '' && preg_match('/^([A-Z])(\d+)(X.+)$/', $currentValue, $m)) {
            $prefix = $m[1] . $m[2];
            return $prefix . $newBaseFnsku;
        }

        return $newBaseFnsku;
    }
}