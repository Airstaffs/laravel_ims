<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SystemDesignController;
use App\Http\Controllers\UserController;


Route::get('/', function () {
    return view('welcome');
});


Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login');
Route::get('/dashboard/Systemdashboard', function () {
    return view('dashboard.Systemdashboard');
})->middleware('auth'); // Protect the dashboard route


Route::post('/add-user', [UserController::class, 'store'])->name('add-user');
Route::post('/update-system-design', [SystemDesignController::class, 'update'])->name('update.system.design');

Route::get('/Systemmodule/{module}Module/{moduleName}', function ($module, $moduleName) {
    $availableModules = ['Order', 'Unreceived', 'Receiving', 'Labeling', 'Validation', 'Testing', 'Cleaning', 'Packing', 'Stockroom'];

    if (in_array($moduleName, $availableModules)) {
        return view("Systemmodule.{$module}Module.{$moduleName}");
    }

    abort(404);
})->name('modules');