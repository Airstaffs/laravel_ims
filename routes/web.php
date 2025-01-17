<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SystemDesignController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StoreController;
use App\Http\Models\Store;
use App\Http\Controllers\AttendanceController;

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


Route::get('/get-stores', [StoreController::class, 'getStores']);
Route::get('/get-store/{id}', [StoreController::class, 'getStoreID'])->name('get-store');
Route::post('/update-store/{id}', [StoreController::class, 'updateStore'])->name('update-store');

Route::post('/add-store', [StoreController::class, 'addstore'])->name('add-store');
Route::delete('/delete-store/{id}', [StoreController::class, 'delete'])->name('delete-store');

Route::get('/dashboard/Systemdashboard', [AttendanceController::class, 'attendance']);
Route::post('/attendance/clockin', [AttendanceController::class, 'clockIn'])->name('attendance.clockin');
Route::post('/attendance/clockout', [AttendanceController::class, 'clockOut'])->name('attendance.clockout');