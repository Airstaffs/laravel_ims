<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AsinMapping;
use Illuminate\Support\Facades\DB;

class AsinMappingController extends Controller
{
    // ✅ Get all mappings (class_name => [asin_code,...])
    public function index()
    {
        $m = AsinMapping::select('class_name','asin_code')->get()->groupBy('class_name');
        $data = $m->map(fn($items) => $items->pluck('asin_code'))->toArray();
        return response()->json($data);
    }

    // 🔎 Optional: get one class only
    public function show($className)
    {
        $codes = AsinMapping::where('class_name',$className)->pluck('asin_code')->values();
        return response()->json($codes);
    }

    // ➕ Add a new mapping
    public function store(Request $request)
    {
        $request->validate([
            'class_name' => 'required|string',
            // 10 alphanumerics (Amazon ASIN format)
            'asin_code'  => ['required','string','regex:/^[A-Za-z0-9]{10}$/'],
        ]);

        // preserve formatting for UI, but trim
        $className = trim($request->class_name);
        // ASINs canonicalized to UPPER
        $asinCode  = strtoupper(trim($request->asin_code));

        // ❗ Case-insensitive duplicate check
        $existing = AsinMapping::whereRaw('UPPER(asin_code) = ?', [$asinCode])->first();
        if ($existing) {
            return response()->json([
                'error' => "ASIN '{$asinCode}' is already assigned to '{$existing->class_name}'."
            ], 409);
        }

        AsinMapping::create([
            'class_name' => $className,
            'asin_code'  => $asinCode,
        ]);

        $this->exportMappings();

        return response()->json(['message' => 'ASIN added successfully']);
    }

    // 🗑 Delete a single ASIN (by code)
    public function destroy($asin_code)
    {
        $asin = strtoupper(trim($asin_code));
        $record = AsinMapping::whereRaw('UPPER(asin_code) = ?', [$asin])->first();

        if (!$record) {
            return response()->json(['error' => "ASIN '{$asin}' not found."], 404);
        }

        $record->delete();
        $this->exportMappings();

        return response()->json(['message' => "ASIN '{$asin}' deleted successfully"]);
    }

    // 🧹 Delete ALL mappings for a class
    public function destroyByClass($className)
    {
        $className = trim($className);
        $deletedCount = DB::table('asin_mappings')->where('class_name', $className)->delete();

        // 🔁 keep Python in sync
        $this->exportMappings();

        return response()->json([
            'message' => "🧹 Deleted {$deletedCount} ASIN mappings for '{$className}'.",
        ]);
    }

    // 🔄 Export asin_mapping.json for Python
    private function exportMappings()
    {
        $m = AsinMapping::select('class_name','asin_code')->get()->groupBy('class_name');
        // Uppercase codes; keep class_name as-is
        $data = $m->map(fn($items) => $items->pluck('asin_code')->map('strtoupper'))->toArray();

        file_put_contents(
            base_path('asin_mapping.json'),
            json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
        );
    }
    
}
