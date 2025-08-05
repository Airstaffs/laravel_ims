<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class HrController extends Controller
{
    public function getEmployees()
    {
        $employees = DB::table('tbluser')
            ->select('id', 'username as name', 'office_role as position')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($employees);
    }
}