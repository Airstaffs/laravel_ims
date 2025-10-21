<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\tblproduct;

class tblproductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $location = $request->query('location', ''); // Default to 'stockroom' if not provided
        $perPage = $request->query('per_page', 10); // Default to 10 items per page

        $query = tblproduct::where('ProductModuleLoc', $location);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ProductTitle', 'like', "%{$search}%")
                    ->orWhere('ASINviewer', 'like', "%{$search}%")
                    ->orWhere('serialnumber', 'like', "%{$search}%")
                    ->orWhere('FNSKUviewer', 'like', "%{$search}%");
            });
        }

        // Use dynamic perPage value
        $data = $query->paginate($perPage);

        return response()->json([
            'data' => $data->items(),
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ]);
    }

    public function fetchPerFnsku(Request $request)
    {
        $search = $request->query('search');
        $location = $request->query('location', 'Stockroom');
        $perPage = (int) $request->query('per_page', 10);

        $query = DB::table('tblproduct as p')
            ->leftJoin('tblfnsku as f', 'p.FNSKUviewer', '=', 'f.FNSKU')
            ->leftJoin('tblasin as a', 'f.ASIN', '=', 'a.ASIN')
            ->selectRaw("
            f.FNSKU,
            f.MSKU,
            f.grading,
            COALESCE(p.warehouselocation, p.shelvesnumber) AS location,

            /* newly added columns from tblfnsku */
            f.reservedstatus     AS reserved_status,
            COALESCE(f.Unfulfillable, 0) AS unfulfillable,
            COALESCE(f.Inbound, 0)       AS inbound,
            f.InboundStatus      AS inbound_status,
            COALESCE(f.Outbound, 0)      AS outbound,
            COALESCE(f.Reserved, 0)      AS reserved,

            COUNT(p.ProductID) AS total_items
        ")
            ->where('p.ProductModuleLoc', $location)
            ->groupBy(
                'f.FNSKU',
                'f.MSKU',
                'f.grading',
                'p.warehouselocation',
                'p.shelvesnumber',
                'f.reservedstatus',
                'f.Unfulfillable',
                'f.Inbound',
                'f.InboundStatus',
                'f.Outbound',
                'f.Reserved'
            );

        // Optional search filter
        if (!empty($search)) {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('p.ProductTitle', 'like', $like)
                    ->orWhere('f.FNSKU', 'like', $like)
                    ->orWhere('f.MSKU', 'like', $like)
                    ->orWhere('a.ASIN', 'like', $like)
                    ->orWhere('p.serialnumber', 'like', $like);
            });
        }

        $data = $query->paginate($perPage);

        // Format the output
        $items = collect($data->items())->map(function ($row) {
            return [
                'FNSKU' => $row->FNSKU,
                'MSKU' => $row->MSKU,
                'grading' => $row->grading,
                'location' => $row->location,
                'reserved_status' => $row->reserved_status,
                'unfulfillable' => (int) $row->unfulfillable,
                'inbound' => (int) $row->inbound,
                'inbound_status' => $row->inbound_status,
                'outbound' => (int) $row->outbound,
                'reserved' => (int) $row->reserved,
                'total_items' => (int) $row->total_items,
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ]);
    }

}
