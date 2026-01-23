<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsinMappingController;
use App\Http\Controllers\TrainingProxyController;
use App\Http\Controllers\DatasetProxyController;

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
 | 🧩 ASIN CRUD (used by AsinAssignModal)
 ========================================================= */

Route::post(
    '/asin-mappings',
    [AsinMappingController::class, 'store']
);

Route::delete(
    '/asin-mappings/{asin}',
    [AsinMappingController::class, 'destroy']
);

/* =========================================================
 | 📂 Dataset Manager (Vue → Laravel → Training Server)
 ========================================================= */

Route::get('/datasets', [DatasetProxyController::class, 'index']);
Route::delete('/datasets/{name}', [DatasetProxyController::class, 'destroy']);


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

    /* 🖼 Class images */
    Route::get('/class-image/{class}/{file}', [TrainingProxyController::class, 'classImage'])
        ->where('file', '.*');

    /* 🚀 Start training */
    Route::post('/start-training', [TrainingProxyController::class, 'startTraining']);

    /* 📡 Training logs (SSE) */
    Route::get('/training-stream', [TrainingProxyController::class, 'trainingStream']);

    /* 🖼 Training images */
    Route::get('/training-image/{model}/{file}', [TrainingProxyController::class, 'trainingImage'])
        ->where('file', '.*');

    /* 📊 Metrics */
    Route::get('/training-metrics/{model}', [TrainingProxyController::class, 'trainingMetrics']);

    Route::get('/training-images/{model}', [TrainingProxyController::class, 'trainingImages']);

    /* 🧠 Update / reload model */
    Route::post('/update-model', [TrainingProxyController::class, 'updateModel']);

    /* ❌ Cancel training (FIXED) */
    Route::post('/cancel-training', [TrainingProxyController::class, 'cancelTraining']);

    Route::post('/test-model', [TrainingProxyController::class, 'testModel']);

    /* 🖼 Dataset images (list) */
    Route::get(
        '/images/{folder}/{class}',
        [TrainingProxyController::class, 'listImages']
    );

    Route::get(
    '/image/{folder}/{class}/{file}',
        [TrainingProxyController::class, 'datasetImage']
    )->where('file', '.*');

    /* ➕ Upload image */
    Route::post(
        '/upload-image/{folder}/{class}',
        [TrainingProxyController::class, 'uploadImage']
    );

    /* 🗑 Delete image */
    Route::delete(
        '/delete-image/{folder}/{class}/{file}',
        [TrainingProxyController::class, 'deleteImage']
    )->where('file', '.*');

});

Route::post('/fbm-orders-invoice', [PrintInvoiceController::class, 'printInvoice']);
