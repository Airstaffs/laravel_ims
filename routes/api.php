<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsinMappingController;
use App\Http\Controllers\TrainingProxyController;

/*
|--------------------------------------------------------------------------
| Stateless API Routes
|--------------------------------------------------------------------------
*/

/* =========================================================
 | 🧩 ASIN Mapping
 ========================================================= */

Route::delete(
    '/asin-mappings/class/{className}',
    [AsinMappingController::class, 'destroyByClass']
);

Route::get('/asin-details/{className}', function ($className) {
    $path = base_path('asin_mapping.json');

    if (!file_exists($path)) {
        return response()->json(['error' => 'asin_mapping.json not found'], 404);
    }

    $json = json_decode(file_get_contents($path), true);

    if (!isset($json[$className])) {
        return response()->json(['error' => 'ASIN not found'], 404);
    }

    return response()->json([
        'asins' => $json[$className],
        'title' => '',
        'brand' => '',
    ]);
});

/* =========================================================
 | 🤖 TRAINING SERVER (Laravel → Proxy → FastAPI)
 | Prefix keeps frontend clean: /api/training/*
 ========================================================= */

Route::prefix('training')->group(function () {

    /* ❤️ Health check */
    Route::get('/ping', [TrainingProxyController::class, 'ping']);

    /* 📁 Dataset / classes */
    Route::get('/class-folders', [TrainingProxyController::class, 'classFolders']);

    /* 📦 Upload dataset */
    Route::post('/upload-dataset', [TrainingProxyController::class, 'uploadDataset']);

    Route::get('/class-image/{class}/{file}', [TrainingProxyController::class, 'classImage'])
    ->where('file', '.*');

    /* 🚀 Start training */
    Route::post('/start-training', [TrainingProxyController::class, 'startTraining']);

    /* 📡 Training logs (SSE) */
    Route::get('/training-stream', [TrainingProxyController::class, 'trainingStream']);

    /* 🖼 Training images */
    Route::get('/training-images/{model}', [TrainingProxyController::class, 'trainingImages']);

    Route::get('/training-image/{model}/{file}', [TrainingProxyController::class, 'trainingImage'])
        ->where('file', '.*');

    /* 📊 Metrics */
    Route::get('/training-metrics/{model}', [TrainingProxyController::class, 'trainingMetrics']);

    /* 🧠 Update / reload model */
    Route::post('/update-model', [TrainingProxyController::class, 'updateModel']);
});
