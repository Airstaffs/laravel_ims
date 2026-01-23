<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class DatasetProxyController extends Controller
{
    private function trainingUrl(string $path = ''): string
    {
        return rtrim(config('services.training.url'), '/') . $path;
    }

    /* 📂 List datasets */
    public function index()
    {
        $res = Http::timeout(30)->get(
            $this->trainingUrl('/api/datasets')
        );

        return response()->json($res->json(), $res->status());
    }

    /* 🗑 Delete dataset */
    public function destroy($name)
    {
        $response = Http::delete(
            $this->trainingServerUrl . "/api/delete-dataset/" . urlencode($name)
        );

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to delete dataset'
            ], 500);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Dataset deleted'
        ]);
    }

}
