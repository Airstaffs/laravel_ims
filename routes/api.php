<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsinMappingController;

// 👇 All routes here are stateless (no CSRF)
Route::delete('/asin-mappings/class/{className}', [AsinMappingController::class, 'destroyByClass']);

Route::get('/asin-details/{className}', function($className) {
    $path = base_path('asin_mapping.json');

    if (!file_exists($path)) {
        return response()->json(['error' => 'asin_mapping.json not found'], 404);
    }

    $json = json_decode(file_get_contents($path), true);

    if (!isset($json[$className])) {
        return response()->json(['error' => 'ASIN not found'], 404);
    }

    $asins = $json[$className];

    return response()->json([
        "asins" => $asins,
        "title" => "",
        "brand" => ""
    ]);
});


