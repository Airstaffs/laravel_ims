<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingProxyController extends Controller
{
    /* ===============================
     | 🔗 Base training server URL
     =============================== */
    private function trainingUrl(string $path = ''): string
    {
        return rtrim(config('services.training.url'), '/') . $path;
    }

    /* ===============================
     | ❤️ Health check
     =============================== */
    public function ping()
    {
        $res = Http::timeout(10)->get($this->trainingUrl('/ping'));
        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 📁 Dataset class folders
     =============================== */
    public function classFolders()
    {
        $res = Http::timeout(30)->get($this->trainingUrl('/api/class-folders'));
        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 📦 Upload dataset (ZIP / files)
     =============================== */
    public function uploadDataset(Request $request)
    {
        if (!$request->hasFile('dataset')) {
            return response()->json([
                'error' => 'No dataset file received'
            ], 400);
        }

        $file = $request->file('dataset');

        $res = Http::timeout(0)
            ->asMultipart()
            ->attach(
                'dataset',
                fopen($file->getPathname(), 'r'),
                $file->getClientOriginalName()
            )
            ->post(
                $this->trainingUrl('/api/upload-dataset'),
                [
                    'split' => (int) $request->input('split', 80),
                ]
            );

        return response()->json($res->json(), $res->status());
    }

    public function classImage($class, $file)
    {
        $base = rtrim(config('services.training.url'), '/');

        $url = $base . '/api/class-image/' . rawurlencode($class) . '/' . rawurlencode($file);

        $response = Http::withOptions([
            'stream' => true,
            'verify' => false,
        ])->get($url);

        return response()->stream(
            function () use ($response) {
                echo $response->body();
            },
            $response->status(),
            [
                'Content-Type' => $response->header('Content-Type') ?? 'image/jpeg',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }


    /* ===============================
     | 🚀 Start training (JSON)
     =============================== */
    public function startTraining(Request $request)
    {
        $payload = [
            'epochs'       => (int) $request->input('epochs', 30),
            'model_name'   => $request->input('model_name', 'asin_model'),
            'auto_replace' => (bool) $request->input('auto_replace', true),
            'use_gpu'      => (bool) $request->input('use_gpu', true),
        ];

        $res = Http::timeout(0)->post(
            $this->trainingUrl('/api/start-training'),
            $payload
        );

        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 📡 Training logs (SSE proxy)
     =============================== */
    public function trainingStream(Request $request)
    {
        $url = $this->trainingUrl('/api/training-stream') . '?' . http_build_query($request->query());

        return new StreamedResponse(function () use ($url) {
            $response = Http::withOptions([
                'stream'  => true,
                'timeout' => 0,
            ])->get($url);

            if (!$response->ok()) {
                echo "data: [ERROR] Training server unavailable\n\n";
                flush();
                return;
            }

            $body = $response->toPsrResponse()->getBody();

            while (!$body->eof()) {
                echo $body->read(1024);
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'        => 'text/event-stream',
            'Cache-Control'       => 'no-cache',
            'Connection'          => 'keep-alive',
            'X-Accel-Buffering'   => 'no', // 🔥 REQUIRED for Nginx SSE
        ]);
    }

    /* ===============================
     | 🖼 Training images (list)
     =============================== */
    public function trainingImages(string $model)
    {
        $res = Http::timeout(30)->get(
            $this->trainingUrl('/api/training-images/' . rawurlencode($model))
        );

        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 🖼 Single training image
     =============================== */
    public function trainingImage(string $model, string $file)
    {
        $url = $this->trainingUrl(
            '/api/training-image/' .
            rawurlencode($model) . '/' .
            rawurlencode($file)
        );

        $res = Http::timeout(30)->get($url);

        if (!$res->ok()) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        return response($res->body(), 200)
            ->header('Content-Type', $res->header('Content-Type', 'image/jpeg'))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /* ===============================
     | 📊 Training metrics
     =============================== */
    public function trainingMetrics(string $model)
    {
        $res = Http::timeout(30)->get(
            $this->trainingUrl('/api/training-metrics/' . rawurlencode($model))
        );

        return response()->json($res->json(), $res->status());
    }

    /* ===============================
     | 🧠 Update / reload model
     =============================== */
    public function updateModel()
    {
        $res = Http::timeout(60)->post(
            $this->trainingUrl('/api/update-model')
        );

        return response()->json($res->json(), $res->status());
    }
}
