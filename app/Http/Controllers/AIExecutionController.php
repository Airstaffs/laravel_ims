<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AIExecutionController extends Controller
{
    private function backendAiUrl(string $path = ''): string
    {
        return rtrim(config('services.backend_ai.url'), '/') . $path;
    }

    /* ===============================
     | 🔍 Serial number detection
     =============================== */
    public function detect(Request $request)
    {
        $file = $request->file('file');

        $res = Http::timeout(120)
            ->attach(
                'file',
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName()
            )
            ->post($this->backendAiUrl('/detect'));

        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 📷 Camera frame OCR
     =============================== */
    public function detectCameraFrame(Request $request)
    {
        $file = $request->file('file');

        $res = Http::timeout(30)
            ->attach(
                'file',
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName()
            )
            ->post($this->backendAiUrl('/detect-camera-frame'));

        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 🧠 ASIN prediction
     =============================== */
    public function asinTest(Request $request)
    {
        $file = $request->file('image');

        $res = Http::timeout(60)
            ->attach(
                'image',
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName()
            )
            ->post($this->backendAiUrl('/api/test-model'));

        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 🔄 Update ASIN model (ADMIN)
     =============================== */
    public function updateAsinModel(Request $request)
    {
        $file = $request->file('file');

        $res = Http::timeout(120)
            ->attach(
                'file',
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName()
            )
            ->post($this->backendAiUrl('/api/update-model'));

        return response()->json($res->json(), $res->status());
    }
}
