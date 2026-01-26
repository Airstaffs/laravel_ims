<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class DatasetProxyController extends Controller
{
    /* ===============================
     | 🔗 Training server URL
     =============================== */
    private function trainingUrl(string $path = ''): string
    {
        return rtrim(config('services.training.url'), '/') . $path;
    }

    /* ===============================
     | 📂 List datasets
     =============================== */
    public function index()
    {
        $res = Http::timeout(30)->get(
            $this->trainingUrl('/api/datasets')
        );

        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 🗑 Delete dataset (FIXED)
     =============================== */
    public function destroy(string $name)
    {
        $res = Http::timeout(30)->delete(
            $this->trainingUrl('/api/delete-dataset/' . rawurlencode($name))
        );

        if ($res->failed()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete dataset',
                'details' => $res->json(),
            ], $res->status());
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'Dataset deleted successfully',
        ]);
    }
}
