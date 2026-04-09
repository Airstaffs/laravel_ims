<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends BasetablesController
{
    public function recordChecklist(Request $request)
    {
        DB::table('tblreceivedchecklist')->insert([
            'trackingnumber' => $request->trackingNumber,
            'serialnumber' => $request->serialNumbers[0] ?? null,
            'serialnumberb' => $request->serialNumbers[1] ?? null,
            'serialnumberc' => $request->serialNumbers[2] ?? null,
            'serialnumberd' => $request->serialNumbers[3] ?? null,
            'serialnumbere' => $request->serialNumbers[4] ?? null,
            'pass_fail_result' => $request->passFailResult,
            'correct_on_order' => $request->correctOnOrder,
            'condition_on_arrival' => $request->condition,
            'condition_notes' => $request->conditionNotes,
            'pcn_number' => $request->pcnNumber,
            'basket_number' => $request->basketNumber,
            'ProductID' => $request->productId,
            'rtcounter' => $request->rtcounter,
            'received_by' => auth()->user()->name ?? 'Unknown',
            'date_received' => now()->toDateString(),
        ]);
    }

    public function checklistLogs(Request $request)
    {
        // Base from tblproduct so items without a checklist still appear
        $query = DB::table('tblproduct as prod')
            ->leftJoin('tblreceivedchecklist as chk',
                DB::raw('CONVERT(prod.ProductID USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                '=',
                DB::raw('CONVERT(chk.ProductID USING utf8mb4) COLLATE utf8mb4_unicode_ci')
            )
            ->leftJoin('tblitemprocesshistory as hist_edit', function ($join) {
                $join->on('prod.rtcounter', '=', 'hist_edit.rtcounter')
                    ->where('hist_edit.Module', '=', 'Labeling')
                    ->where('hist_edit.Action', '=', 'Update');
            })
            ->leftJoin('tblitemprocesshistory as hist_val', function ($join) {
                $join->on('prod.rtcounter', '=', 'hist_val.rtcounter')
                    ->where('hist_val.Module', '=', 'Labeling')
                    ->where('hist_val.Action', '=', 'Location Change')
                    ->where('hist_val.newLocation', 'like', '%to Validation%');
            })
            ->leftJoin('tblitemprocesshistory as hist_stk', function ($join) {
                $join->on('prod.rtcounter', '=', 'hist_stk.rtcounter')
                    ->where('hist_stk.Module', '=', 'Labeling')
                    ->where('hist_stk.Action', '=', 'Location Change')
                    ->where('hist_stk.newLocation', 'like', '%to Stockroom%');
            })
            ->leftJoin('tbltestingworklogs as twl',
                DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                '=',
                DB::raw('CONVERT(twl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
            )
            ->leftJoin('tblcleaningWorkLogs as cwl',
                DB::raw('CONVERT(prod.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci'),
                '=',
                DB::raw('CONVERT(cwl.rtcounter USING utf8mb4) COLLATE utf8mb4_unicode_ci')
            )
            ->select([
                // ── Checklist ──────────────────────────────────────────────
                'chk.checklist_id',
                'chk.trackingnumber',
                'chk.serialnumber',
                'chk.serialnumberb',
                'chk.serialnumberc',
                'chk.serialnumberd',
                'chk.serialnumbere',
                'chk.pass_fail_result',
                'chk.correct_on_order',
                'chk.condition_on_arrival',
                'chk.condition_notes',
                'chk.date_received',
                'chk.received_by',
                'chk.pcn_number',
                'chk.basket_number',

                // ── Product ────────────────────────────────────────────────
                'prod.ProductID',
                'prod.rtcounter',
                'prod.ProductTitle as product_name',
                'prod.ASINviewer as asin',
                'prod.MSKUviewer as msku',
                'prod.RPN as rpn',
                'prod.PRD as prd',
                'prod.priorityrank as priority_rank',
                'prod.ProductModuleLoc as current_location',
                'prod.Username as labelled_by',
                'prod.lastDateUpdate as date_labelled',
                'prod.stickernote as sticker_note',
                'prod.EmployeeNote as employee_note',
                DB::raw("COALESCE(NULLIF(TRIM(prod.ChangedtoFNSKU), ''), NULLIF(TRIM(prod.FNSKUviewer), '')) as fnsku"),

                // ── Edit history ───────────────────────────────────────────
                'hist_edit.editDate as last_edited_at',
                'hist_edit.employeeName as last_edited_by',
                'hist_edit.oldLocation as edit_before',
                'hist_edit.newLocation as edit_after',

                // ── Move to Validation history ─────────────────────────────
                'hist_val.editDate as moved_to_validation_at',
                'hist_val.employeeName as moved_to_validation_by',

                // ── Move to Stockroom history ──────────────────────────────
                'hist_stk.editDate as moved_to_stockroom_at',
                'hist_stk.employeeName as moved_to_stockroom_by',

                // ── Flags ──────────────────────────────────────────────────
                DB::raw("CASE WHEN prod.MSKUviewer IS NOT NULL AND prod.MSKUviewer != '' THEN 1 ELSE 0 END as passed_labeling"),
                DB::raw("CASE WHEN prod.ProductModuleLoc IN ('Testing','Cleaning','Stockroom','Validation','Sold') THEN 1 ELSE 0 END as passed_testing"),
                DB::raw("CASE WHEN prod.ProductModuleLoc IN ('Cleaning','Packaging','Stockroom','Validation','Sold') THEN 1 ELSE 0 END as passed_cleaning"),

                // ── Testing work log ───────────────────────────────────────
                'twl.tested_by',
                'twl.date_tested',
                'twl.test_result',
                DB::raw('twl.field_values as testing_field_values'),

                // ── Cleaning work log ──────────────────────────────────────
                'cwl.cleaned_by',
                'cwl.date_cleaned',
                'cwl.mark_done as cleaning_done',
                DB::raw('cwl.category_values as cleaning_category_values'),
            ]);

        if ($request->serial) {
            $query->where('chk.serialnumber', 'like', "%{$request->serial}%");
        }
        if ($request->asin) {
            $query->where('prod.ASINviewer', 'like', "%{$request->asin}%");
        }
        if ($request->tracking) {
            $query->where('chk.trackingnumber', 'like', "%{$request->tracking}%");
        }
        if ($request->status) {
            $query->where('chk.pass_fail_result', $request->status);
        }
        if ($request->from) {
            $query->whereDate('chk.date_received', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('chk.date_received', '<=', $request->to);
        }

        return response()->json(
            $query->orderByDesc('chk.date_received')->paginate(10)
        );
    }
}
