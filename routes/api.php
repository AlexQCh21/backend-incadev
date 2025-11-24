<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\RecursosHumanos\EmployeeController;
use App\Http\Controllers\RecursosHumanos\OfferController;
use App\Http\Controllers\RecursosHumanos\ApplicantController;


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test', function (Request $request) {
        return response()->json(['message' => 'API funcionando correctamente']);
    });

});

Route::middleware(['auth:sanctum', 'role:admin|system_viewer'])->get('/viewer-dashboard', function () {
    return response()->json(['message' => 'Bienvenido al panel general']);
});

Route::get('/dashboard/groups', [DashboardController::class, 'getGroups']);

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

//DASHBOARD ROUTES
Route::prefix('dashboard')->group(function () {
    Route::get('/data', [DashboardController::class, 'getDashboardData']);
    Route::get('/export-data', [DashboardController::class, 'exportDashboardData']);
});

//FINANZAS ROUTES
Route::prefix('finanzas')->group(function () {
    Route::get('/balance-general', [\App\Http\Controllers\Finanzas\BalanceGeneralController::class, 'index']);
});


// Rutas de Recursos Humanos
Route::prefix('rrhh')->group(function () {
    
    // Empleados
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::patch('/employees/{id}', [EmployeeController::class, 'update']);
    
    // Contratos
    Route::post('/employees/{id}/contracts', [EmployeeController::class, 'createContract']);
    Route::patch('/employees/{id}/deactivate', [EmployeeController::class, 'deactivate']);
    Route::post('/employees/{id}/activate', [EmployeeController::class, 'activate']);
    Route::patch('/contracts/{contractId}', [EmployeeController::class, 'updateContract']);

    // Ofertas
    Route::prefix('offers')
    ->controller(OfferController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/stats', 'stats');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/close', 'close');
        
        // Aplicaciones
        Route::get('/{offerId}/applications', 'getApplications');
        Route::post('/applications', 'storeApplication');
    });

    // Postulantes
    Route::prefix('applicants')
    ->controller(ApplicantController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/stats', 'stats');
        Route::get('/{id}', 'show');
        Route::get('/{applicantId}/applications', 'getApplications');
        Route::put('/applications/{applicationId}/status', 'updateApplicationStatus');
        Route::get('/offers/{offerId}/applications', 'getApplicationsByOffer');
        Route::put('/applications/{applicationId}/status', 'updateApplicationStatus');
    });
});