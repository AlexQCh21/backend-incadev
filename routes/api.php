<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Payments\PaymentsController;
use App\Http\Controllers\Gestion_Academica\StudentController;
use App\Http\Controllers\Finanzas\FinancialReportsController;
use App\Http\Controllers\AcademicProcesses\TeacherGroupController;
use App\Http\Controllers\AcademicProcesses\EnrollmentStatusController;
use App\Http\Controllers\Gestion_Academica\AcademicHistoryController;
use App\Http\Controllers\Gestion_Academica\EnrollmentController;
use App\Http\Controllers\AcademicProcesses\ModuleController;
use App\Http\Controllers\AcademicProcesses\GroupController;

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
    // Teacher Groups
    Route::get('/teacher-groups', [TeacherGroupController::class, 'index']);
    Route::post('/teacher-groups/assign', [TeacherGroupController::class, 'assign']);
    Route::delete('/teacher-groups/remove', [TeacherGroupController::class, 'remove']);

    // Enrollment Status
    Route::get('/enrollment-status', [EnrollmentStatusController::class, 'index']);
    Route::get('/enrollment-status/{id}', [EnrollmentStatusController::class, 'show']);
    Route::put('/enrollment-status/{id}/payment-status', [EnrollmentStatusController::class, 'updatePaymentStatus']);
    Route::put('/enrollment-status/{id}/academic-status', [EnrollmentStatusController::class, 'updateAcademicStatus']);
    Route::put('/enrollment-status/{id}/result', [EnrollmentStatusController::class, 'updateEnrollmentResult']);

    //Module
    Route::get('/courses', [ModuleController::class, 'getCourses']);
    Route::get('/course-version/{courseVersionId}', [ModuleController::class, 'getCourseVersion']);
    Route::post('/', [ModuleController::class, 'store']);
    Route::put('/{id}', [ModuleController::class, 'update']);
    Route::delete('/{id}', [ModuleController::class, 'destroy']);
    Route::post('/reorder', [ModuleController::class, 'reorder']);

    //Groups
    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::get('/course-versions', [GroupController::class, 'getCourseVersions']);
        Route::get('/{id}', [GroupController::class, 'show']);
        Route::post('/', [GroupController::class, 'store']);
        Route::put('/{id}', [GroupController::class, 'update']);
        Route::delete('/{id}', [GroupController::class, 'destroy']);
        Route::get('/{id}/statistics', [GroupController::class, 'statistics']);
    });
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

Route::prefix('financial-reports')->group(function () {
    // Ruta principal para reportes contables
    Route::get('/report', [FinancialReportsController::class, 'getReport']);
    Route::get('/balance-general', [FinancialReportsController::class, 'getBalanceGeneral']);
});

// Rutas de compatibilidad (opcionales)
Route::prefix('reports')->group(function () {
    Route::get('/', [FinancialReportsController::class, 'getReport']);
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

    // Rutas principales de Historial Académico
    Route::prefix('historial-academico')->group(function () {
        Route::get('/', [AcademicHistoryController::class, 'index']);
        Route::get('/{id}', [AcademicHistoryController::class, 'show']);
        Route::get('/{id}/cursos', [AcademicHistoryController::class, 'getCourses']);
        Route::get('/{id}/certificados', [AcademicHistoryController::class, 'getCertificates']);
        Route::get('/exportar/csv', [AcademicHistoryController::class, 'exportCSV']);
        Route::get('/exportar/datos', [AcademicHistoryController::class, 'exportData']);
    });

    // Rutas de enrollments
    Route::prefix('matriculas')->group(function () {
        Route::get('/', [EnrollmentController::class, 'index']);
        Route::post('/', [EnrollmentController::class, 'store']);
        Route::get('/statistics', [EnrollmentController::class, 'statistics']);
        Route::get('/export-csv', [EnrollmentController::class, 'exportCsv']);
        Route::get('/export-pdf', [EnrollmentController::class, 'exportPdf']);
        Route::get('/export-data', [EnrollmentController::class, 'getExportData']);
        Route::get('/{id}', [EnrollmentController::class, 'show']);
        Route::put('/{id}', [EnrollmentController::class, 'update']);
        Route::patch('/{id}/payment-status', [EnrollmentController::class, 'updatePaymentStatus']);
        Route::patch('/{id}/academic-status', [EnrollmentController::class, 'updateAcademicStatus']);
        Route::delete('/{id}', [EnrollmentController::class, 'destroy']);
    });
});
