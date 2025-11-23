<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\Gestion_Academica\StudentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test', function (Request $request) {
        return response()->json(['message' => 'API funcionando correctamente']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin|system_viewer'])->get('/viewer-dashboard', function () {
    return response()->json(['message' => 'Bienvenido al panel general']);
});

Route::get('/dashboard/groups', [DashboardController::class, 'getGroups']);

//DASHBOARD ROUTES
Route::prefix('dashboard')->group(function () {
    Route::get('/data', [DashboardController::class, 'getDashboardData']);
    Route::get('/export-data', [DashboardController::class, 'exportDashboardData']);
});

//ACADEMIC MANAGEMENT ROUTES
Route::prefix('gestion-academica')->group(function () {
    // IMPORTANTE: Rutas específicas primero
    Route::get('/estudiantes/statistics', [StudentController::class, 'statistics']);
    Route::get('/estudiantes/export/csv', [StudentController::class, 'exportCsv']);
    Route::get('/estudiantes/export/pdf', [StudentController::class, 'exportPdf']);
    Route::get('/estudiantes/export-data', [StudentController::class, 'getExportData']);
    Route::get('/estudiantes/{id}/enrollments', [StudentController::class, 'enrollments']);

    // Rutas generales después
    Route::get('/estudiantes', [StudentController::class, 'index']);
    Route::post('/estudiantes', [StudentController::class, 'store']);
    Route::get('/estudiantes/{id}', [StudentController::class, 'show']);
    Route::put('/estudiantes/{id}', [StudentController::class, 'update']);
    Route::delete('/estudiantes/{id}', [StudentController::class, 'destroy']);
});

//ACADEMIC PROCESSES ROUTES (sin autenticación por ahora)
Route::prefix('academic-processes')->group(function () {
    Route::get('/teacher-groups', [\App\Http\Controllers\AcademicProcesses\TeacherGroupController::class, 'index']);
    Route::post('/teacher-groups/assign', [\App\Http\Controllers\AcademicProcesses\TeacherGroupController::class, 'assign']);
    Route::delete('/teacher-groups/remove', [\App\Http\Controllers\AcademicProcesses\TeacherGroupController::class, 'remove']);
});

// PAYMENTS ROUTES (sin autenticación por ahora)
Route::prefix('pagos')->group(function () {
    Route::get('/', [PaymentsController::class, 'index']);
    Route::get('/export-csv', [PaymentsController::class, 'exportCsv']);
    Route::get('/export-pdf', [PaymentsController::class, 'exportPdf']);
    Route::get('/export-data', [PaymentsController::class, 'getExportData']);
    Route::get('/{id}/invoice', [PaymentsController::class, 'downloadInvoice']);
    Route::get('/{id}/invoice-data', [PaymentsController::class, 'getInvoiceData']);
    Route::get('/{id}/check-evidence', [PaymentsController::class, 'checkEvidence']);
    Route::get('/{id}/evidence', [PaymentsController::class, 'getEvidence']);
    Route::post('/{id}/approve', [PaymentsController::class, 'approve']);
    Route::post('/{id}/reject', [PaymentsController::class, 'reject']);
});

//FINANZAS ROUTES
Route::prefix('finanzas')->group(function () {
    Route::get('/balance-general', [\App\Http\Controllers\Finanzas\BalanceGeneralController::class, 'index']);
});

//DOCUMENT MANAGEMENT ROUTES
Route::prefix('gestion-documentaria')->group(function () {
    // Rutas específicas primero
    Route::get('/documentos/statistics', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'statistics']);
    Route::get('/documentos/export/csv', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'exportCsv']);
    Route::get('/documentos/export-data', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'getExportData']);
    Route::get('/documentos/{id}/download', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'download']);

    // Rutas generales
    Route::get('/documentos', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'index']);
    Route::post('/documentos', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'store']);
    Route::get('/documentos/{id}', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'show']);
    Route::put('/documentos/{id}', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'update']);
    Route::delete('/documentos/{id}', [\App\Http\Controllers\DocumentManagement\DocumentController::class, 'destroy']);
});
