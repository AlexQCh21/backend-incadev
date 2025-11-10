<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test', function (Request $request) {
        return response()->json(['message' => 'API funcionando correctamente']);
    });

});

Route::middleware(['auth:sanctum', 'role:admin|system_viewer'])->get('/viewer-dashboard', function () {
    return response()->json(['message' => 'Bienvenido al panel general']);
});

Route::get('/dashboard/groups', [DashboardController::class, 'getGroups']);