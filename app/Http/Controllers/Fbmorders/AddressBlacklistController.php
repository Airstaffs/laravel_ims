<?php

namespace App\Http\Controllers\FbmOrders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressBlacklistController extends Controller
{
    /**
     * GET LIST
     */
    public function list(Request $request)
    {
        $data = $request->validate([
            'module_name' => ['required', 'string'],
            'subject_name' => ['required', 'string'],
        ]);

        try {
            $rows = DB::table('tbladdressblacklist')
                ->where('module_name', $data['module_name'])
                ->where('subject_name', $data['subject_name'])
                ->orderBy('id', 'DESC')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $rows
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blacklist rules.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SAVE RULE
     */
    public function save(Request $request)
    {
        $data = $request->validate([
            'module_name' => ['required', 'string'],
            'subject_name' => ['required', 'string'],
            'detect_word' => ['required', 'string'],
            'color' => ['required', 'string'],
        ]);

        try {
            // prevent duplicates (same word + module + subject)
            $exists = DB::table('tbladdressblacklist')
                ->where('module_name', $data['module_name'])
                ->where('subject_name', $data['subject_name'])
                ->where('detect_word', $data['detect_word'])
                ->first();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rule already exists.'
                ], 400);
            }

            DB::table('tbladdressblacklist')->insert([
                'module_name' => $data['module_name'],
                'subject_name' => $data['subject_name'],
                'detect_word' => strtoupper($data['detect_word']), // normalize
                'color' => $data['color'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rule saved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save rule.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE RULE
     */
    public function delete(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        try {
            DB::table('tbladdressblacklist')
                ->where('id', $data['id'])
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rule deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete rule.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}