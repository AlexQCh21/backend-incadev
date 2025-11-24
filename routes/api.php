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
use App\Http\Controllers\AcademicProcesses\AcademicSettingsController;
use App\Http\Controllers\AcademicProcesses\CourseController;
use App\Http\Controllers\AcademicProcesses\CourseVersionController;
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

//DASHBOARD ROUTES
Route::prefix('dashboard')->group(function () {
    Route::get('/data', [DashboardController::class, 'getDashboardData']);
    Route::get('/export-data', [DashboardController::class, 'exportDashboardData']);
});

//INDICATORS ROUTES (KPIs)
Route::prefix('indicadores')->group(function () {
    Route::get('/', [App\Http\Controllers\Indicators\KpiController::class, 'index']);
    Route::put('/{id}/goal', [App\Http\Controllers\Indicators\KpiController::class, 'updateGoal']);
    Route::post('/recalculate', [App\Http\Controllers\Indicators\KpiController::class, 'recalculate']);
    Route::get('/export-data', [App\Http\Controllers\Indicators\KpiController::class, 'exportData']);
});


//ACADEMIC PROCESSES ROUTES (sin autenticación por ahora)
Route::prefix('academic-processes')->group(function () {
    //Academic Settings
    Route::get('/academic-settings', [AcademicSettingsController::class, 'index']);
    Route::put('/academic-settings', [AcademicSettingsController::class, 'update']);

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

    //Cursos
    Route::prefix('courses-management')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::get('/statistics', [CourseController::class, 'statistics']);
        Route::get('/export/csv', [CourseController::class, 'exportCsv']);
        Route::get('/export/pdf', [CourseController::class, 'exportPdf']);
        Route::get('/export-data', [CourseController::class, 'getExportData']);
        Route::post('/', [CourseController::class, 'store']);
        Route::get('/{id}', [CourseController::class, 'show']);
        Route::put('/{id}', [CourseController::class, 'update']);
        Route::delete('/{id}', [CourseController::class, 'destroy']);
    });
    //Versiones
    Route::prefix('course-versions')->group(function () {
        Route::get('/', [CourseVersionController::class, 'index']);
        Route::get('/statistics', [CourseVersionController::class, 'statistics']);
        Route::get('/courses', [CourseVersionController::class, 'getCourses']); // Para dropdown
        Route::get('/export/csv', [CourseVersionController::class, 'exportCsv']);
        Route::get('/export/pdf', [CourseVersionController::class, 'exportPdf']);
        Route::post('/', [CourseVersionController::class, 'store']);
        Route::get('/{id}', [CourseVersionController::class, 'show']);
        Route::put('/{id}', [CourseVersionController::class, 'update']);
        Route::delete('/{id}', [CourseVersionController::class, 'destroy']);
    });
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

    // Rutas para Institute Directors
    Route::apiResource('institute-directors', \App\Http\Controllers\DocumentManagement\InstituteDirectorController::class);
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

    // Postulantes - ORDEN CORRECTO
    Route::prefix('applicants')
    ->controller(ApplicantController::class)
    ->group(function () {
        // ✅ RUTAS ESPECÍFICAS PRIMERO
        Route::get('/roles', 'getAvailableRoles'); // ✅ PRIMERO - antes de /{id}
        Route::get('/stats', 'stats');
        Route::get('/offers/{offerId}/applications', 'getApplicationsByOffer');
        Route::put('/applications/{applicationId}/status', 'updateApplicationStatus');
        
        // ✅ RUTAS CON PARÁMETROS DESPUÉS
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::get('/{applicantId}/applications', 'getApplications');
    });
});