<?php

namespace App\Http\Controllers;

use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HistoryTrackingController extends Controller
{
    protected $historyTable = 'tblitemprocesshistory';

    public function logHistory(
        string $module,
        string $action,
        ?string $oldLocation = null,
        ?string $newLocation = null,
        ?string $employeeName = null
    ): int {
        $employeeName = $employeeName ?? $this->getAuthenticatedEmployeeName();

        $historyId = DB::table($this->historyTable)->insertGetId([
            'rtcounter' => $this->generateRtCounter(),
            'employeeName' => $employeeName,
            'editDate' => $this->getCurrentDateTime(),
            'Module' => $module,
            'Action' => $action,
            'oldLocation' => $oldLocation,
            'newLocation' => $newLocation,
        ]);

        return $historyId;
    }

    public function logHistoryBatch(array $data): int
    {
        $data['employeeName'] = $data['employeeName'] ?? $this->getAuthenticatedEmployeeName();
        $data['rtcounter'] = $data['rtcounter'] ?? $this->generateRtCounter();
        $data['editDate'] = $data['editDate'] ?? $this->getCurrentDateTime();

        $historyId = DB::table($this->historyTable)->insertGetId($data);

        return $historyId;
    }

    public function getHistory(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $module = $request->input('module', '');
        $action = $request->input('action', '');
        $employeeName = $request->input('employee_name', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $search = $request->input('search', '');

        $query = DB::table($this->historyTable)
            ->when($module, function ($q) use ($module) {
                return $q->where('Module', $module);
            })
            ->when($action, function ($q) use ($action) {
                return $q->where('Action', $action);
            })
            ->when($employeeName, function ($q) use ($employeeName) {
                return $q->where('employeeName', 'like', "%{$employeeName}%");
            })
            ->when($dateFrom, function ($q) use ($dateFrom) {
                return $q->where('editDate', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                return $q->where('editDate', '<=', $dateTo);
            })
            ->when($search, function ($q) use ($search) {
                return $q->where(function ($query) use ($search) {
                    $query->where('Module', 'like', "%{$search}%")
                        ->orWhere('Action', 'like', "%{$search}%")
                        ->orWhere('employeeName', 'like', "%{$search}%")
                        ->orWhere('oldLocation', 'like', "%{$search}%")
                        ->orWhere('newLocation', 'like', "%{$search}%")
                        ->orWhere('rtcounter', 'like', "%{$search}%");
                });
            })
            ->orderBy('editDate', 'desc')
            ->paginate($perPage);

        return response()->json($query);
    }

    public function getModuleHistory(string $module, int $perPage = 10)
    {
        return DB::table($this->historyTable)
            ->where('Module', $module)
            ->orderBy('editDate', 'desc')
            ->paginate($perPage);
    }

    public function getEmployeeHistory(string $employeeName, int $perPage = 10)
    {
        return DB::table($this->historyTable)
            ->where('employeeName', $employeeName)
            ->orderBy('editDate', 'desc')
            ->paginate($perPage);
    }

    public function getHistoryStats(Request $request)
    {
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        $query = DB::table($this->historyTable);

        if ($dateFrom) {
            $query->where('editDate', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('editDate', '<=', $dateTo);
        }

        $stats = [
            'total_actions' => $query->count(),
            'by_module' => (clone $query)->select('Module', DB::raw('count(*) as count'))
                ->groupBy('Module')
                ->get(),
            'by_action' => (clone $query)->select('Action', DB::raw('count(*) as count'))
                ->groupBy('Action')
                ->get(),
            'by_employee' => (clone $query)->select('employeeName', DB::raw('count(*) as count'))
                ->groupBy('employeeName')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }

    public function cleanupOldHistory(int $daysOld = 90): int
    {
        $cutoffDate = now()->subDays($daysOld)->format('Y-m-d H:i:s');

        return DB::table($this->historyTable)
            ->where('editDate', '<', $cutoffDate)
            ->delete();
    }

    protected function generateRtCounter(): int
    {
        $lastCounter = DB::table($this->historyTable)
            ->max('rtcounter');

        return ($lastCounter ?? 0) + 1;
    }

    protected function getCurrentDateTime(): string
    {
        $timezone = config('app.timezone', 'UTC');
        $date = new DateTime('now', new DateTimeZone($timezone));

        return $date->format('Y-m-d H:i:s');
    }

    protected function getAuthenticatedEmployeeName(): string
    {
        $user = Auth::user();

        if (! $user) {
            return 'System';
        }

        return $user->username ?? 'Unknown';
    }
}
