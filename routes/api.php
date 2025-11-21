<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\Finanzas\FinancialReportsController;

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

Route::prefix('financial-reports')->group(function () {
    Route::get('/metrics', [FinancialReportsController::class, 'getFinancialMetrics']);
    Route::get('/monthly-revenue', [FinancialReportsController::class, 'getMonthlyRevenueData']);
    Route::get('/revenue-distribution', [FinancialReportsController::class, 'getRevenueDistribution']);
    Route::get('/courses-detailed', [FinancialReportsController::class, 'getCoursesDetailedData']);
    Route::get('/executive-summary', [FinancialReportsController::class, 'getExecutiveSummary']);
    Route::post('/generate-pdf', [FinancialReportsController::class, 'generatePDFReport']);
});