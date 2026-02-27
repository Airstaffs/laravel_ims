<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 

class SupplierController extends BasetablesController
{
    public function index(Request $request)
{
    $perPage = min($request->input('per_page', 15), 100);
    $search  = $request->input('search', '');
    $page    = $request->input('page', 1);

    // Grab only NEW sellers not yet in tblsuppliers — all done in DB, no PHP loops
    DB::statement("
        INSERT IGNORE INTO tblsuppliers (name, contact, address1, address2, email, websiteAddress)
        SELECT DISTINCT CONVERT(seller USING utf8mb4) COLLATE utf8mb4_general_ci, NULL, NULL, NULL, NULL, NULL
        FROM {$this->productTable}
        WHERE seller IS NOT NULL
          AND seller != ''
          AND seller NOT IN (SELECT name COLLATE utf8mb4_general_ci FROM tblsuppliers)
    ");

    // Query tblsuppliers with search + pagination
    $query = DB::table('tblsuppliers');

    if ($search) {
        $query->where('name', 'like', "%{$search}%");
    }

    $suppliers = $query->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

    return response()->json($suppliers);
}

public function updateSupplier(Request $request)
{
    $validated = $request->validate([
        'supplierName'           => 'required|string',
        'supplierContact'        => 'nullable|string',
        'supplierAddress1'       => 'nullable|string',
        'supplierAddress2'       => 'nullable|string',
        'supplierEmail'          => 'nullable|email',
        'supplierWebsiteAddress' => 'nullable|string',
    ]);

    // Check if supplier exists
    $existingSupplier = DB::table('tblsuppliers')
        ->where('name', $validated['supplierName'])
        ->first();

    if (!$existingSupplier) {
        return response()->json([
            'success' => false,
            'message' => 'Supplier not found',
        ], 404);
    }

    $updatedSupplier = DB::table('tblsuppliers')
        ->where('name', $validated['supplierName'])
        ->update([
            'contact'        => $validated['supplierContact'],
            'address1'       => $validated['supplierAddress1'],
            'address2'       => $validated['supplierAddress2'],
            'email'          => $validated['supplierEmail'],
            'websiteAddress' => $validated['supplierWebsiteAddress'],
        ]);

    return response()->json([
        'success' => true,
        'message' => 'Supplier ' . $validated['supplierName'] . ' updated successfully',
        'data' => $updatedSupplier
    ]);
}

}