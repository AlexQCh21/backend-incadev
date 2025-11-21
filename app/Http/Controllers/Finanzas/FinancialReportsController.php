<?php

namespace App\Http\Controllers\Finanzas;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\EnrollmentPayment;
use IncadevUns\CoreDomain\Models\PayrollExpense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialReportsController extends Controller
{
    /**
     * Obtener métricas financieras principales
     */
    public function getFinancialMetrics(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $metrics = [
            'total_revenue' => $this->getTotalRevenue($filters),
            'active_students' => $this->getActiveStudentsCount($filters),
            'courses_sold' => $this->getCoursesSoldCount($filters),
            'conversion_rate' => $this->getConversionRate($filters),
            'total_expenses' => $this->getTotalExpenses($filters),
            'net_profit' => $this->getNetProfit($filters),
        ];

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'period' => $filters['time_range'] ?? 'month' // Usar time_range en lugar de period
        ]);
    }

    /**
     * Obtener datos para gráfico de ingresos mensuales
     */
    public function getMonthlyRevenueData(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $revenueData = EnrollmentPayment::where('status', 'approved')
            ->when($filters['start_date'], function ($query) use ($filters) {
                $query->where('operation_date', '>=', $filters['start_date']);
            })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('operation_date', '<=', $filters['end_date']);
            })
            ->when($filters['course_id'], function ($query) use ($filters) {
                $query->whereHas('enrollment.group.courseVersion', function ($q) use ($filters) {
                    $q->where('course_id', $filters['course_id']);
                });
            })
            ->select(
                DB::raw('YEAR(operation_date) as year'),
                DB::raw('MONTH(operation_date) as month'),
                DB::raw('SUM(amount) as revenue')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::create($item->year, $item->month, 1)->format('M'),
                    'revenue' => (float) $item->revenue,
                    'expenses' => $this->getMonthlyExpenses($item->year, $item->month)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $revenueData
        ]);
    }

    /**
     * Obtener distribución de ingresos por categoría/curso
     */
    public function getRevenueDistribution(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $distribution = Course::with(['versions.enrollments.payments' => function ($query) use ($filters) {
            $query->where('status', 'approved')
                ->when($filters['start_date'], function ($q) use ($filters) {
                    $q->where('operation_date', '>=', $filters['start_date']);
                })
                ->when($filters['end_date'], function ($q) use ($filters) {
                    $q->where('operation_date', '<=', $filters['end_date']);
                });
        }])
            ->get()
            ->map(function ($course) {
                $revenue = $course->versions->flatMap(function ($version) {
                    return $version->enrollments->flatMap(function ($enrollment) {
                        return $enrollment->payments->sum('amount');
                    });
                })->sum();

                return [
                    'name' => $course->name,
                    'value' => (float) $revenue,
                    'enrollments' => $course->versions->flatMap->enrollments->count(),
                    'completion_rate' => $this->calculateCompletionRate($course)
                ];
            })
            ->filter(function ($item) {
                return $item['value'] > 0;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $distribution
        ]);
    }

    /**
     * Obtener datos detallados por curso
     */
    public function getCoursesDetailedData(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $courses = CourseVersion::with(['course', 'enrollments.payments', 'enrollments.result'])
            ->when($filters['course_id'], function ($query) use ($filters) {
                $query->where('course_id', $filters['course_id']);
            })
            ->get()
            ->map(function ($version) use ($filters) {
                $enrollments = $version->enrollments;

                // Filtrar por fechas si es necesario
                if ($filters['start_date'] || $filters['end_date']) {
                    $enrollments = $enrollments->filter(function ($enrollment) use ($filters) {
                        $enrollmentDate = $enrollment->created_at;
                        return (!$filters['start_date'] || $enrollmentDate >= $filters['start_date']) &&
                            (!$filters['end_date'] || $enrollmentDate <= $filters['end_date']);
                    });
                }

                $revenue = $enrollments->flatMap->payments
                    ->where('status', 'approved')
                    ->sum('amount');

                $completedEnrollments = $enrollments->where('academic_status', 'completed');
                $completionRate = $enrollments->count() > 0 ?
                    ($completedEnrollments->count() / $enrollments->count()) * 100 : 0;

                $averageRating = $completedEnrollments->flatMap->result
                    ->where('status', 'approved')
                    ->average('final_grade');

                return [
                    'id' => $version->id,
                    'name' => $version->name,
                    'course_name' => $version->course->name,
                    'revenue' => (float) $revenue,
                    'enrollments' => $enrollments->count(),
                    'completion_rate' => round($completionRate, 1),
                    'rating' => round($averageRating, 1),
                    'category' => $this->getCourseCategory($version->course),
                    'price' => (float) $version->price
                ];
            })
            ->filter(function ($item) {
                return $item['enrollments'] > 0;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $courses
        ]);
    }

    /**
     * Generar reporte PDF
     */
    public function generatePDFReport(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $reportType = $request->get('report_type', 'general');

        try {
            $reportData = $this->generateReportData($filters, $reportType);

            // Aquí integrarías con tu librería de PDF (DomPDF, etc.)
            $pdfUrl = $this->generatePDF($reportData, $reportType);

            return response()->json([
                'success' => true,
                'pdf_url' => $pdfUrl,
                'message' => 'Reporte generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen ejecutivo
     */
    public function getExecutiveSummary(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $totalRevenue = $this->getTotalRevenue($filters);
        $totalExpenses = $this->getTotalExpenses($filters);
        $netProfit = $totalRevenue - $totalExpenses;

        $summary = [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
            'roi' => $totalExpenses > 0 ? ($netProfit / $totalExpenses) * 100 : 0,
            'active_courses' => $this->getActiveCoursesCount($filters),
            'completion_rate' => $this->getOverallCompletionRate($filters),
            'average_revenue_per_student' => $this->getAverageRevenuePerStudent($filters)
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary
        ]);
    }

    // ============== MÉTODOS PRIVADOS DE APOYO ==============

    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'time_range' => 'sometimes|in:week,month,quarter,year,custom',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'course_id' => 'sometimes|exists:courses,id',
            'report_type' => 'sometimes|in:general,revenue,expenses,courses,students',
            'category' => 'sometimes|string'
        ]);

        // Establecer fechas por defecto según el time_range
        $period = $this->calculateDateRange($validated['time_range'] ?? 'month');

        return array_merge($validated, $period);
    }

    private function calculateDateRange(string $timeRange): array
    {
        return match ($timeRange) {
            'week' => [
                'start_date' => Carbon::now()->subWeek(),
                'end_date' => Carbon::now()
            ],
            'month' => [
                'start_date' => Carbon::now()->subMonth(),
                'end_date' => Carbon::now()
            ],
            'quarter' => [
                'start_date' => Carbon::now()->subQuarter(),
                'end_date' => Carbon::now()
            ],
            'year' => [
                'start_date' => Carbon::now()->subYear(),
                'end_date' => Carbon::now()
            ],
            default => [
                'start_date' => Carbon::now()->subMonth(),
                'end_date' => Carbon::now()
            ]
        };
    }

    private function getTotalRevenue(array $filters): float
    {
        return (float) EnrollmentPayment::where('status', 'approved')
            ->when(isset($filters['start_date']) && $filters['start_date'], function ($query) use ($filters) {
                $query->where('operation_date', '>=', $filters['start_date']);
            })
            ->when(isset($filters['end_date']) && $filters['end_date'], function ($query) use ($filters) {
                $query->where('operation_date', '<=', $filters['end_date']);
            })
            ->when(isset($filters['course_id']) && $filters['course_id'], function ($query) use ($filters) {
                $query->whereHas('enrollment.group.courseVersion', function ($q) use ($filters) {
                    $q->where('course_id', $filters['course_id']);
                });
            })
            ->sum('amount');
    }

    private function getTotalExpenses(array $filters): float
    {
        return (float) PayrollExpense::when($filters['start_date'], function ($query) use ($filters) {
            $query->where('date', '>=', $filters['start_date']);
        })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('date', '<=', $filters['end_date']);
            })
            ->sum('amount');
    }

    private function getActiveStudentsCount(array $filters): int
    {
        return Enrollment::where('academic_status', 'active')
            ->when(isset($filters['start_date']) && $filters['start_date'], function ($query) use ($filters) {
                $query->where('created_at', '>=', $filters['start_date']);
            })
            ->when(isset($filters['end_date']) && $filters['end_date'], function ($query) use ($filters) {
                $query->where('created_at', '<=', $filters['end_date']);
            })
            ->when(isset($filters['course_id']) && $filters['course_id'], function ($query) use ($filters) {
                $query->whereHas('group.courseVersion', function ($q) use ($filters) {
                    $q->where('course_id', $filters['course_id']);
                });
            })
            ->count();
    }


    private function getCoursesSoldCount(array $filters): int
    {
        return Enrollment::when(isset($filters['start_date']) && $filters['start_date'], function ($query) use ($filters) {
            $query->where('created_at', '>=', $filters['start_date']);
        })
            ->when(isset($filters['end_date']) && $filters['end_date'], function ($query) use ($filters) {
                $query->where('created_at', '<=', $filters['end_date']);
            })
            ->when(isset($filters['course_id']) && $filters['course_id'], function ($query) use ($filters) {
                $query->whereHas('group.courseVersion', function ($q) use ($filters) {
                    $q->where('course_id', $filters['course_id']);
                });
            })
            ->count();
    }

    private function getConversionRate(array $filters): float
    {
        $totalEnrollments = $this->getCoursesSoldCount($filters);
        $completedEnrollments = Enrollment::where('academic_status', 'completed')
            ->when($filters, function ($query) use ($filters) {
                // Aplicar mismos filtros
            })
            ->count();

        return $totalEnrollments > 0 ? ($completedEnrollments / $totalEnrollments) * 100 : 0;
    }

    private function getNetProfit(array $filters): float
    {
        return $this->getTotalRevenue($filters) - $this->getTotalExpenses($filters);
    }

    private function getMonthlyExpenses(int $year, int $month): float
    {
        return (float) PayrollExpense::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');
    }

    private function calculateCompletionRate(Course $course): float
    {
        $totalEnrollments = $course->versions->flatMap->enrollments->count();
        $completedEnrollments = $course->versions->flatMap->enrollments
            ->where('academic_status', 'completed')
            ->count();

        return $totalEnrollments > 0 ? ($completedEnrollments / $totalEnrollments) * 100 : 0;
    }

    private function getCourseCategory(Course $course): string
    {
        // Aquí puedes implementar lógica para categorizar cursos
        // Por ahora devolvemos una categoría basada en el nombre
        $name = strtolower($course->name);

        if (str_contains($name, 'programación') || str_contains($name, 'coding') || str_contains($name, 'react') || str_contains($name, 'python')) {
            return 'Programación';
        } elseif (str_contains($name, 'diseño') || str_contains($name, 'ui') || str_contains($name, 'ux')) {
            return 'Diseño';
        } elseif (str_contains($name, 'marketing') || str_contains($name, 'digital')) {
            return 'Marketing';
        } elseif (str_contains($name, 'negocio') || str_contains($name, 'gestión') || str_contains($name, 'project')) {
            return 'Negocios';
        }

        return 'General';
    }

    private function generateReportData(array $filters, string $reportType): array
    {
        return [
            'filters' => $filters,
            'report_type' => $reportType,
            'metrics' => [
                'total_revenue' => $this->getTotalRevenue($filters),
                'total_expenses' => $this->getTotalExpenses($filters),
                'net_profit' => $this->getNetProfit($filters),
                'active_students' => $this->getActiveStudentsCount($filters),
            ],
            'generated_at' => Carbon::now()->toDateTimeString(),
            'generated_by' => Auth::check() ? Auth::user()->name : 'Sistema'
        ];
    }

    private function generatePDF(array $reportData, string $reportType): string
    {
        // Implementar generación de PDF con DomPDF o similar
        // Por ahora devolvemos una URL simulada
        return url('/reports/financial/' . uniqid() . '.pdf');
    }

    private function getActiveCoursesCount(array $filters): int
    {
        return CourseVersion::where('status', 'active')
            ->when($filters['course_id'], function ($query) use ($filters) {
                $query->where('course_id', $filters['course_id']);
            })
            ->count();
    }

    private function getOverallCompletionRate(array $filters): float
    {
        $totalEnrollments = Enrollment::when($filters['start_date'], function ($query) use ($filters) {
            $query->where('created_at', '>=', $filters['start_date']);
        })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('created_at', '<=', $filters['end_date']);
            })
            ->count();

        $completedEnrollments = Enrollment::where('academic_status', 'completed')
            ->when($filters['start_date'], function ($query) use ($filters) {
                $query->where('created_at', '>=', $filters['start_date']);
            })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('created_at', '<=', $filters['end_date']);
            })
            ->count();

        return $totalEnrollments > 0 ? ($completedEnrollments / $totalEnrollments) * 100 : 0;
    }

    private function getAverageRevenuePerStudent(array $filters): float
    {
        $activeStudents = $this->getActiveStudentsCount($filters);
        $totalRevenue = $this->getTotalRevenue($filters);

        return $activeStudents > 0 ? $totalRevenue / $activeStudents : 0;
    }
}
