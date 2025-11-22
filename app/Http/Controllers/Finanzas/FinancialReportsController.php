<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\User;
use IncadevUns\CoreDomain\Models\Contract;
use IncadevUns\CoreDomain\Models\PayrollExpense;
use IncadevUns\CoreDomain\Models\EnrollmentPayment;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialReportsController extends Controller
{
    /**
     * Obtener reporte contable según el tipo solicitado
     */
    public function getReport(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $reportType = $request->get('report_type', 'financial');

        try {
            $data = match ($reportType) {
                'financial' => $this->getFinancialReport($filters),
                'payroll' => $this->getPayrollReport($filters),
                'revenue' => $this->getRevenueReport($filters),
                'profitability' => $this->getProfitabilityReport($filters),
                default => $this->getFinancialReport($filters)
            };

            return response()->json([
                'success' => true,
                'data' => $data,
                'report_type' => $reportType,
                'period' => $filters['time_range'] ?? 'month'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte Financiero General (Balance)
     */
    private function getFinancialReport(array $filters): array
    {
        $totalRevenue = $this->getTotalRevenue($filters);
        $totalPayroll = $this->getTotalPayrollExpenses($filters);
        $netProfit = $totalRevenue - $totalPayroll;

        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_payroll_expenses' => $totalPayroll,
                'net_profit' => $netProfit,
                'profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
                'balance' => $netProfit,
                'active_students' => $this->getActiveStudentsCount($filters),
                'active_courses' => $this->getActiveCoursesCount($filters),
            ],
            'monthly_data' => $this->getMonthlyFinancialData($filters),
        ];
    }


    /**
     * Reporte de Nómina Detallado
     */
    /**
     * Reporte de Nómina con Balance comparativo
     */
    private function getPayrollReport(array $filters): array
    {
        // Obtener gastos de nómina con datos del empleado y rol
        $payrollDetails = PayrollExpense::with(['contract.user.roles'])
            ->when(
                $filters['start_date'],
                fn($q) =>
                $q->where('date', '>=', $filters['start_date'])
            )
            ->when(
                $filters['end_date'],
                fn($q) =>
                $q->where('date', '<=', $filters['end_date'])
            )
            ->get()
            ->map(function ($expense) {
                $user = $expense->contract->user;
                $mainRole = $user->roles->first()?->name ?? 'Sin rol';

                return [
                    'id' => $expense->id,
                    'employee' => $user->name,
                    'email' => $user->email,
                    'role' => $mainRole,
                    'staff_type' => $expense->contract->staff_type?->value,
                    'payment_type' => $expense->contract->payment_type?->value,
                    'amount' => (float) $expense->amount,
                    'date' => $expense->date->format('Y-m-d'),
                    'description' => $expense->description,
                ];
            });

        // Totales de nómina
        $totalPayroll = $payrollDetails->sum('amount');

        // Ingresos por matrículas en el mismo período
        $totalRevenue = $this->getTotalRevenue($filters);

        // Resultado neto del período
        $netProfit = $totalRevenue - $totalPayroll;

        // Resumen estructurado para frontend/PDF
        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_payroll_expenses' => $totalPayroll,
                'net_profit' => $netProfit,
                'profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
                'balance' => $netProfit,
                'total_employees' => $payrollDetails->unique('email')->count(),
                'total_payments' => $payrollDetails->count(),
            ],
            'details' => $payrollDetails,
        ];
    }



    /**
     * Reporte de Ingresos - Versión Mejorada
     */
    private function getRevenueReport(array $filters): array
    {
        $totalRevenue = $this->getTotalRevenue($filters);

        // Si no hay ingresos, retornar estructura vacía pero con datos básicos
        if ($totalRevenue <= 0) {
            return [
                'summary' => [
                    'total_revenue' => 0,
                    'revenue_sources_count' => 0,
                    'average_revenue_per_source' => 0,
                ],
                'revenue_by_course' => [],
                'recent_payments' => $this->getRecentPayments($filters),
                'monthly_data' => $this->getMonthlyFinancialData($filters),
            ];
        }

        // Intentar obtener ingresos por curso
        try {
            $revenueByCourse = $this->getRevenueByCourse($filters, $totalRevenue);
        } catch (\Exception $e) {
            // Log::error('Error getting revenue by course: ' . $e->getMessage());
            $revenueByCourse = [];
        }

        // Si no hay datos por curso, obtener pagos recientes
        $recentPayments = $this->getRecentPayments($filters);

        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'revenue_sources_count' => count($revenueByCourse),
                'average_revenue_per_source' => count($revenueByCourse) > 0 ?
                    collect($revenueByCourse)->average('revenue') : 0,
                'recent_payments_count' => count($recentPayments),
            ],
            'revenue_by_course' => $revenueByCourse,
            'recent_payments' => $recentPayments,
            'details' => EnrollmentPayment::with(['enrollment.user', 'enrollment.group.courseVersion.course'])
                ->where('status', 'approved')
                ->when($filters['start_date'], function ($query) use ($filters) {
                    $query->where('operation_date', '>=', $filters['start_date']);
                })
                ->when($filters['end_date'], function ($query) use ($filters) {
                    $query->where('operation_date', '<=', $filters['end_date']);
                })
                ->orderBy('operation_date', 'desc')
                ->get()
                ->map(function ($payment) {
                    return [
                        'student' => $payment->enrollment->user->name ?? 'N/A',
                        'email' => $payment->enrollment->user->email ?? 'N/A',
                        'course' => $payment->enrollment->group->courseVersion->course->name ?? 'N/A',
                        'amount' => (float) $payment->amount,
                        'date' => $payment->operation_date->format('Y-m-d'),
                        'type' => 'Pago de matrícula'
                    ];
                }),
            'monthly_data' => $this->getMonthlyFinancialData($filters),
        ];
    }

    public function getBalanceGeneral(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);

        $ingresosVerificados = $this->getTotalRevenue($filters);
        $ingresosPendientes = EnrollmentPayment::where('status', 'pending')->sum('amount');
        $gastosNomina = $this->getTotalPayrollExpenses($filters);

        $activos = $this->getRevenueByCourse($filters, max($ingresosVerificados, 1));
        $pasivos = $this->getPayrollReport($filters)['details'];

        return response()->json([
            'success' => true,
            'data' => [
                'resumen' => [
                    'ingresos_verificados' => $ingresosVerificados,
                    'pagos_pendientes' => $ingresosPendientes,
                    'ingresos_mes_actual' => $ingresosVerificados,
                    'gastos_nomina' => $gastosNomina,
                    'patrimonio_neto' => $ingresosVerificados - $gastosNomina,
                    'cursos_activos' => count($activos),
                    'matriculas_pagadas' => Enrollment::whereHas('payments', function ($q) {
                        $q->where('status', 'approved');
                    })->count(),
                ],
                'activos' => $activos,
                'pasivos' => $pasivos,
                'indicadores' => [
                    'eficiencia_cobranza' => $ingresosVerificados > 0
                        ? ($ingresosVerificados / ($ingresosVerificados + $ingresosPendientes)) * 100
                        : 0,
                    'margen_neto' => $ingresosVerificados > 0
                        ? (($ingresosVerificados - $gastosNomina) / $ingresosVerificados) * 100
                        : 0,
                    'ingreso_promedio_por_curso' => count($activos) > 0
                        ? $ingresosVerificados / count($activos)
                        : 0,
                    'tasa_retencion' => 90 // Valor temporal
                ]
            ],
        ]);
    }


    /**
     * Obtener ingresos por curso
     */
    private function getRevenueByCourse(array $filters, float $totalRevenue): array
    {
        return Course::with(['versions.groups.enrollments.payments'])
            ->get()
            ->map(function ($course) use ($filters, $totalRevenue) {
                $revenue = 0;

                // Método seguro para calcular ingresos
                foreach ($course->versions ?? [] as $version) {
                    foreach ($version->groups ?? [] as $group) {
                        foreach ($group->enrollments ?? [] as $enrollment) {
                            $payments = $enrollment->payments()
                                ->where('status', 'approved')
                                ->when($filters['start_date'], function ($q) use ($filters) {
                                    $q->where('operation_date', '>=', $filters['start_date']);
                                })
                                ->when($filters['end_date'], function ($q) use ($filters) {
                                    $q->where('operation_date', '<=', $filters['end_date']);
                                })
                                ->sum('amount');

                            $revenue += $payments;
                        }
                    }
                }

                return [
                    'course_name' => $course->name,
                    'revenue' => (float) $revenue,
                    'percentage' => $totalRevenue > 0 ? ($revenue / $totalRevenue) * 100 : 0
                ];
            })
            ->filter(fn($item) => $item['revenue'] > 0)
            ->values()
            ->toArray();
    }

    /**
     * Obtener pagos recientes como fallback
     */
    private function getRecentPayments(array $filters): array
    {
        return EnrollmentPayment::with(['enrollment.user', 'enrollment.group.courseVersion.course'])
            ->where('status', 'approved')
            ->when($filters['start_date'], function ($query) use ($filters) {
                $query->where('operation_date', '>=', $filters['start_date']);
            })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('operation_date', '<=', $filters['end_date']);
            })
            ->orderBy('operation_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'date' => $payment->operation_date->format('Y-m-d'),
                    'student_name' => $payment->enrollment->user->name ?? 'N/A',
                    'course_name' => $payment->enrollment->group->courseVersion->course->name ?? 'N/A',
                    'description' => 'Pago de matrícula'
                ];
            })
            ->toArray();
    }

    /**
     * Reporte de Rentabilidad
     */
    private function getProfitabilityReport(array $filters): array
    {
        $totalRevenue = $this->getTotalRevenue($filters);
        $totalPayroll = $this->getTotalPayrollExpenses($filters);
        $netProfit = $totalRevenue - $totalPayroll;

        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalPayroll,
                'net_profit' => $netProfit,
                'profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
                'roi' => $totalPayroll > 0 ? ($netProfit / $totalPayroll) * 100 : 0,
                'break_even_point' => $this->calculateBreakEvenPoint($totalRevenue, $totalPayroll),
            ],
            'efficiency_metrics' => [
                'revenue_per_student' => $this->getActiveStudentsCount($filters) > 0 ?
                    $totalRevenue / $this->getActiveStudentsCount($filters) : 0,
                'payroll_efficiency' => $totalRevenue > 0 ?
                    ($totalRevenue - $totalPayroll) / $totalRevenue * 100 : 0,
            ]
        ];
    }

    // ============== MÉTODOS AUXILIARES IMPLEMENTADOS ==============

    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'time_range' => 'sometimes|in:month,quarter,year,last_month,last_quarter,last_year',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'report_type' => 'sometimes|in:financial,payroll,revenue,profitability',
        ]);

        $period = $this->calculateDateRange($validated['time_range'] ?? 'month');

        return array_merge($validated, $period);
    }

    private function calculateDateRange(string $timeRange): array
    {
        return match ($timeRange) {
            'month' => [
                'start_date' => Carbon::now()->startOfMonth(),
                'end_date' => Carbon::now()->endOfMonth()
            ],
            'quarter' => [
                'start_date' => Carbon::now()->startOfQuarter(),
                'end_date' => Carbon::now()->endOfQuarter()
            ],
            'year' => [
                'start_date' => Carbon::now()->startOfYear(),
                'end_date' => Carbon::now()->endOfYear()
            ],
            'last_month' => [
                'start_date' => Carbon::now()->subMonth()->startOfMonth(),
                'end_date' => Carbon::now()->subMonth()->endOfMonth()
            ],
            'last_quarter' => [
                'start_date' => Carbon::now()->subQuarter()->startOfQuarter(),
                'end_date' => Carbon::now()->subQuarter()->endOfQuarter()
            ],
            'last_year' => [
                'start_date' => Carbon::now()->subYear()->startOfYear(),
                'end_date' => Carbon::now()->subYear()->endOfYear()
            ],
            default => [
                'start_date' => Carbon::now()->startOfMonth(),
                'end_date' => Carbon::now()->endOfMonth()
            ]
        };
    }

    /**
     * Obtener datos mensuales para gráficos
     */
    private function getMonthlyFinancialData(array $filters): array
    {
        // Ingresos mensuales
        $revenueData = EnrollmentPayment::where('status', 'approved')
            ->when($filters['start_date'], function ($query) use ($filters) {
                $query->where('operation_date', '>=', $filters['start_date']);
            })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('operation_date', '<=', $filters['end_date']);
            })
            ->select(
                DB::raw('YEAR(operation_date) as year'),
                DB::raw('MONTH(operation_date) as month'),
                DB::raw('SUM(amount) as revenue')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        // Gastos de nómina mensuales
        $payrollData = PayrollExpense::when($filters['start_date'], function ($query) use ($filters) {
            $query->where('date', '>=', $filters['start_date']);
        })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('date', '<=', $filters['end_date']);
            })
            ->select(
                DB::raw('YEAR(date) as year'),
                DB::raw('MONTH(date) as month'),
                DB::raw('SUM(amount) as payroll')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        // Combinar datos
        $combinedData = [];

        foreach ($revenueData as $revenue) {
            $payroll = $payrollData->first(function ($item) use ($revenue) {
                return $item->year == $revenue->year && $item->month == $revenue->month;
            });

            $combinedData[] = [
                'month' => Carbon::create($revenue->year, $revenue->month, 1)->format('M Y'),
                'revenue' => (float) $revenue->revenue,
                'payroll' => (float) ($payroll->payroll ?? 0),
                'profit' => (float) $revenue->revenue - ($payroll->payroll ?? 0)
            ];
        }

        // Si no hay datos, crear datos de ejemplo para el último año
        if (empty($combinedData)) {
            for ($i = 11; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $combinedData[] = [
                    'month' => $date->format('M Y'),
                    'revenue' => 0,
                    'payroll' => 0,
                    'profit' => 0
                ];
            }
        }

        return $combinedData;
    }

    private function getTotalRevenue(array $filters): float
    {
        return (float) EnrollmentPayment::where('status', 'approved')
            ->when($filters['start_date'], function ($query) use ($filters) {
                $query->where('operation_date', '>=', $filters['start_date']);
            })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('operation_date', '<=', $filters['end_date']);
            })
            ->sum('amount');
    }

    private function getTotalPayrollExpenses(array $filters): float
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
        return User::role('student')
            ->when($filters['start_date'], function ($query) use ($filters) {
                $query->where('created_at', '>=', $filters['start_date']);
            })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('created_at', '<=', $filters['end_date']);
            })
            ->count();
    }

    private function getActiveCoursesCount(array $filters): int
    {
        // Si la columna 'status' no existe, simplemente cuenta todos los cursos
        // o usa otra columna que sí exista como 'is_active', 'active', etc.

        return Course::when($filters['start_date'], function ($query) use ($filters) {
            $query->where('created_at', '>=', $filters['start_date']);
        })
            ->when($filters['end_date'], function ($query) use ($filters) {
                $query->where('created_at', '<=', $filters['end_date']);
            })
            ->count();
    }

    private function calculateBreakEvenPoint(float $revenue, float $expenses): float
    {
        return $expenses > 0 ? $revenue / $expenses : 0;
    }

    private function generatePDF(array $reportData, string $reportType, array $filters): string
    {
        // URL simulada para el PDF
        return url('/reports/' . $reportType . '/' . uniqid() . '.pdf');
    }
}
