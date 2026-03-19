<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PayrollController extends Controller
{
    // ============================================================
    // PAYSLIPS
    // ============================================================

    /**
     * GET /hr/payslips/new-count
     * Returns count of new (unviewed) released payslips for the logged-in employee
     */
    public function getNewCount()
    {
        $user = auth()->user();
        $count = DB::table('tblpayslips')
            ->where('employee_id', $user->id)
            ->where('employee_status', 'new')
            ->count();

        return response()->json([
            'count' => $count ?? 0,
            'debug_user_id' => $user->id, // remove after testing
        ]);
    }

    /**
     * GET /hr/payslips
     * - HR/Admin: see all payslips (draft + released)
     * - Employee: see only their own released payslips
     */
    public function getPayslips(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $user = auth()->user();

        $query = DB::table('tblpayslips')->orderBy('created_at', 'desc');

        if ($user->isHR()) {
            // HR sees everything
        } else {
            // Employee sees only their own released payslips
            $query->where('employee_id', $user->id)
                ->where('status', 'released');
        }

        // Optional date filters (used by employee modal)
        if ($request->filled('from')) {
            $query->where('cutoff_from', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('cutoff_to', '<=', $request->to);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * POST /hr/payslips
     * HR only — create a new payslip
     */
    public function createPayslip(Request $request)
    {
        if (! auth()->user()->isHR()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        Log::info('Payslip request data:', $request->all());

        try {
            $validated = $request->validate([
                'employee_id' => 'required|integer',
                'employee_name' => 'required|string',
                'payout_date' => 'required|date',
                'cutoff_from' => 'required|date',
                'cutoff_to' => 'required|date',
                'total_days' => 'required|integer',
                'total_hours' => 'required|numeric',
                'hourly_rate' => 'required|numeric',
                'currency' => 'required|string|max:3',
                'basic_pay' => 'required|numeric',
                'regular_holiday_hours' => 'nullable|numeric',
                'regular_holiday_pay' => 'nullable|numeric',
                'special_holiday_hours' => 'nullable|numeric',
                'special_holiday_pay' => 'nullable|numeric',
                'gross_pay' => 'required|numeric',
                'deduction_details' => 'nullable|array',
                // Each item must have name, amount, active, and type (fixed|custom)
                'deduction_details.*.name' => 'required_with:deduction_details|string',
                'deduction_details.*.amount' => 'required_with:deduction_details|numeric|min:0',
                'deduction_details.*.active' => 'required_with:deduction_details|boolean',
                'deduction_details.*.type' => 'required_with:deduction_details|in:fixed,custom',
                'holiday_details' => 'nullable|array',
                'attendance_records' => 'nullable|array',
                'notes' => 'nullable|string|max:65535',
            ]);

            // Recalculate deductions server-side from active items (fixed + custom)
            $totalDeductions = 0;
            if (! empty($validated['deduction_details'])) {
                foreach ($validated['deduction_details'] as $d) {
                    if (! empty($d['active'])) {
                        $totalDeductions += floatval($d['amount'] ?? 0);
                    }
                }
            }

            // Recalculate net_pay server-side
            $netPay = floatval($validated['gross_pay']) - $totalDeductions;

            $payslipId = DB::table('tblpayslips')->insertGetId([
                'employee_id' => $validated['employee_id'],
                'employee_name' => $validated['employee_name'],
                'payout_date' => $validated['payout_date'],
                'cutoff_from' => $validated['cutoff_from'],
                'cutoff_to' => $validated['cutoff_to'],
                'total_days' => $validated['total_days'],
                'total_hours' => $validated['total_hours'],
                'hourly_rate' => $validated['hourly_rate'],
                'currency' => $validated['currency'],
                'basic_pay' => $validated['basic_pay'],
                'regular_holiday_hours' => $validated['regular_holiday_hours'] ?? 0,
                'regular_holiday_pay' => $validated['regular_holiday_pay'] ?? 0,
                'special_holiday_hours' => $validated['special_holiday_hours'] ?? 0,
                'special_holiday_pay' => $validated['special_holiday_pay'] ?? 0,
                'gross_pay' => $validated['gross_pay'],
                'deductions' => $totalDeductions,
                'net_pay' => $netPay,
                'deduction_details' => ! empty($validated['deduction_details'])
                                            ? json_encode($validated['deduction_details'])
                                            : null,
                'holiday_details' => ! empty($validated['holiday_details'])
                                            ? json_encode($validated['holiday_details'])
                                            : null,
                'attendance_records' => ! empty($validated['attendance_records'])
                                            ? json_encode($validated['attendance_records'])
                                            : null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->user()->username ?? 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payslip created successfully.',
                'payslip_id' => $payslipId,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Payslip validation error:', $e->errors());

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Payslip creation error: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payslip.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /hr/payslips/{id}
     * HR only — update a payslip
     */
    public function updatePayslip(Request $request, $id)
    {
        if (! auth()->user()->isHR()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            $validated = $request->validate([
                'employee_id' => 'required|integer',
                'payout_date' => 'required|date',
                'cutoff_from' => 'required|date',
                'cutoff_to' => 'required|date',
                'deduction_details' => 'nullable|array',
                'notes' => 'nullable|string|max:65535',
            ]);

            // Recalculate total from ALL active deductions (fixed + custom)
            $totalDeductions = 0;
            if (! empty($validated['deduction_details'])) {
                foreach ($validated['deduction_details'] as $d) {
                    if (! empty($d['active'])) {
                        $totalDeductions += floatval($d['amount'] ?? 0);
                    }
                }
            }

            // Fetch existing payslip to recalculate net_pay
            $payslip = DB::table('tblpayslips')->where('id', $id)->first();
            if (! $payslip) {
                return response()->json(['message' => 'Payslip not found.'], 404);
            }

            $netPay = floatval($payslip->gross_pay) - $totalDeductions;

            DB::table('tblpayslips')
                ->where('id', $id)
                ->update([
                    'employee_id' => $validated['employee_id'],
                    'payout_date' => $validated['payout_date'],
                    'cutoff_from' => $validated['cutoff_from'],
                    'cutoff_to' => $validated['cutoff_to'],
                    'deductions' => $totalDeductions,
                    'net_pay' => $netPay,              // ← also update net_pay
                    'deduction_details' => ! empty($validated['deduction_details'])
                                            ? json_encode($validated['deduction_details'])
                                            : null,
                    'notes' => $validated['notes'] ?? null,
                    'updated_at' => now(),
                ]);

            return response()->json(['success' => true, 'message' => 'Payslip updated successfully.']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Payslip update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payslip.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /hr/payslips/{id}
     * HR only — delete a payslip
     */
    public function deletePayslip($id)
    {
        if (! auth()->user()->isHR()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            $deleted = DB::table('tblpayslips')->where('id', $id)->delete();

            if (! $deleted) {
                return response()->json(['success' => false, 'message' => 'Payslip not found.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Payslip deleted successfully.']);

        } catch (\Exception $e) {
            Log::error('Payslip delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payslip.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /hr/payslips/{id}/employee-status
     * Employee updates their own status (viewed / acknowledged)
     */
    public function updateEmployeeStatus(Request $request, $id)
    {
        $record = DB::table('tblpayslips')->where('id', $id)->first();

        if (! $record) {
            return response()->json(['message' => 'Payslip not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_status' => 'required|in:new,viewed,acknowledged',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid status.', 'errors' => $validator->errors()], 422);
        }

        // Only allow upgrade: new → viewed → acknowledged (no downgrade)
        $order = ['new' => 0, 'viewed' => 1, 'acknowledged' => 2];
        $current = $order[$record->employee_status] ?? 0;
        $incoming = $order[$request->employee_status] ?? 0;

        if ($incoming > $current) {
            DB::table('tblpayslips')
                ->where('id', $id)
                ->update([
                    'employee_status' => $request->employee_status,
                    'updated_at' => now(),
                ]);
        }

        $updated = DB::table('tblpayslips')->where('id', $id)->first();

        return response()->json([
            'message' => 'Employee status updated.',
            'data' => $updated,
        ]);
    }

    /**
     * PATCH /hr/payslips/{id}/release
     * HR only — release a payslip so the employee can see it
     */
    public function releasePayslip($id)
    {
        if (! auth()->user()->isHR()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            $updated = DB::table('tblpayslips')
                ->where('id', $id)
                ->update([
                    'status' => 'released',
                    'updated_at' => now(),
                ]);

            if (! $updated) {
                return response()->json(['message' => 'Payslip not found.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Payslip released successfully.']);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to release payslip.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /hr/payslips/{id}/status
     * HR only — update payslip status (draft → approved → paid)
     */
    public function updateStatus(Request $request, $id)
    {
        if (! auth()->user()->isHR()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'status' => 'required|in:draft,released',
        ]);

        try {
            $updated = DB::table('tblpayslips')
                ->where('id', $id)
                ->update([
                    'status' => $request->status,
                    'updated_at' => now(),
                ]);

            if (! $updated) {
                return response()->json(['message' => 'Payslip not found.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Payslip status updated.']);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // HOLIDAYS
    // ============================================================

    /**
     * GET /hr/holidays
     */
    public function getHolidays(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $holidays = DB::table('tblholiday')
            ->select('holidayID', 'holidate', 'status', 'title', 'is_recurring')
            ->where('holidate', '>=', $dateFrom)
            ->where('holidate', '<=', $dateTo)
            ->get();

        return response()->json($holidays);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function calculateHoursFromRecord(array $record): float
    {
        try {
            $timeIn = new \DateTime($record['TimeIn']);
            $timeOut = new \DateTime($record['TimeOut']);
            $diff = $timeOut->getTimestamp() - $timeIn->getTimestamp();

            if (! empty($record['shortbreak_start']) && ! empty($record['shortbreak_end'])) {
                $breakStart = new \DateTime($record['shortbreak_start']);
                $breakEnd = new \DateTime($record['shortbreak_end']);
                $diff -= ($breakEnd->getTimestamp() - $breakStart->getTimestamp());
            } elseif (! empty($record['shortbreak_totaltime'])) {
                $diff -= ($record['shortbreak_totaltime'] * 60);
            }

            return round($diff / 3600, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getFixedDeductions()
    {
        if (! auth()->user()->isHR()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            $deductions = DB::table('tblfixeddeductions')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json($deductions);

        } catch (\Exception $e) {
            Log::error('Error fetching fixed deductions: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch fixed deductions.',
            ], 500);
        }
    }
}
