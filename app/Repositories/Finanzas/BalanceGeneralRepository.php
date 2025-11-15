<?php

namespace App\Repositories\Finanzas;

use App\DTOs\Finanzas\BalanceGeneralRepositoryInterface;
use App\DTOs\Finanzas\FiltrosBalanceDTO;
use IncadevUns\CoreDomain\Models\EnrollmentPayment;
use IncadevUns\CoreDomain\Models\PayrollExpense;
use IncadevUns\CoreDomain\Models\CourseVersion;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceGeneralRepository implements BalanceGeneralRepositoryInterface
{
    public function obtenerResumen(FiltrosBalanceDTO $filtros): array
    {
        return [
            'ingresos_verificados' => $this->calcularIngresosVerificados($filtros),
            'pagos_pendientes' => $this->calcularPagosPendientes($filtros),
            'ingresos_mes_actual' => $this->calcularIngresosMesActual($filtros),
            'gastos_nomina' => $this->calcularGastosNomina($filtros),
            'patrimonio_neto' => $this->calcularPatrimonioNeto($filtros),
            'cursos_activos' => $this->contarCursosActivos($filtros),
            'matriculas_pagadas' => $this->contarMatriculasPagadas($filtros)
        ];
    }

    public function obtenerActivos(FiltrosBalanceDTO $filtros): array
    {
        // Usando Eloquent en lugar de SQL crudo
        return CourseVersion::with(['course', 'groups.enrollments.payments'])
            ->where('status', 'published')
            ->when($filtros->cursoId, function ($query) use ($filtros) {
                $query->where('course_id', $filtros->cursoId);
            })
            ->when($filtros->versionId, function ($query) use ($filtros) {
                $query->where('id', $filtros->versionId);
            })
            ->get()
            ->map(function ($courseVersion) use ($filtros) {
                $enrollments = $this->obtenerMatriculasPorVersion($courseVersion->id, $filtros);
                
                return [
                    'id' => $courseVersion->id,
                    'curso' => $courseVersion->course->name . ' - ' . $courseVersion->name,
                    'precio_curso' => $courseVersion->price,
                    'total_matriculas' => $enrollments->count(),
                    'ingresos_verificados' => $this->calcularIngresosVerificadosPorVersion($courseVersion->id, $filtros),
                    'ingresos_pendientes' => $this->calcularPagosPendientesPorVersion($courseVersion->id, $filtros),
                    'estudiantes_activos' => $enrollments->where('academic_status', 'active')->count(),
                    'estudiantes_completados' => $enrollments->where('academic_status', 'completed')->count(),
                ];
            })
            ->toArray();
    }

    public function obtenerPasivos(FiltrosBalanceDTO $filtros): array
    {
        // Usando Eloquent con relaciones
        return PayrollExpense::with('contract')
            ->when($filtros->fechaDesde, function ($query) use ($filtros) {
                $query->where('date', '>=', $filtros->fechaDesde);
            })
            ->when($filtros->fechaHasta, function ($query) use ($filtros) {
                $query->where('date', '<=', $filtros->fechaHasta);
            })
            ->get()
            ->map(function ($payrollExpense) {
                return [
                    'id' => $payrollExpense->id,
                    'contrato' => 'CONT-' . $payrollExpense->contract_id . ' - ' . ($payrollExpense->contract->nombre ?? 'Contrato'),
                    'monto' => $payrollExpense->amount,
                    'fecha' => $payrollExpense->date->toDateString(),
                    'descripcion' => $payrollExpense->description,
                    'tipo' => 'nómina',
                    'estado' => 'pagado'
                ];
            })
            ->toArray();
    }

    public function calcularIndicadores(FiltrosBalanceDTO $filtros): array
    {
        $ingresosVerificados = $this->calcularIngresosVerificados($filtros);
        $pagosPendientes = $this->calcularPagosPendientes($filtros);
        $gastosNomina = $this->calcularGastosNomina($filtros);
        $cursosActivos = $this->contarCursosActivos($filtros);

        return [
            'eficiencia_cobranza' => $this->calcularEficienciaCobranza($ingresosVerificados, $pagosPendientes),
            'margen_neto' => $this->calcularMargenNeto($ingresosVerificados, $gastosNomina),
            'ingreso_promedio_por_curso' => $this->calcularIngresoPromedioPorCurso($ingresosVerificados, $cursosActivos),
            'tasa_retencion' => $this->calcularTasaRetencion($filtros)
        ];
    }

    /**
     * Métodos auxiliares mejorados
     */
    private function calcularIngresosVerificados(FiltrosBalanceDTO $filtros): float
    {
        return EnrollmentPayment::where('status', 'approved')
            ->when($filtros->fechaDesde, function ($query) use ($filtros) {
                $query->where('operation_date', '>=', $filtros->fechaDesde);
            })
            ->when($filtros->fechaHasta, function ($query) use ($filtros) {
                $query->where('operation_date', '<=', $filtros->fechaHasta);
            })
            ->sum('amount');
    }

    private function calcularIngresosVerificadosPorVersion(int $courseVersionId, FiltrosBalanceDTO $filtros): float
    {
        return EnrollmentPayment::where('status', 'approved')
            ->whereHas('enrollment.group', function ($query) use ($courseVersionId) {
                $query->where('course_version_id', $courseVersionId);
            })
            ->when($filtros->fechaDesde, function ($query) use ($filtros) {
                $query->where('operation_date', '>=', $filtros->fechaDesde);
            })
            ->when($filtros->fechaHasta, function ($query) use ($filtros) {
                $query->where('operation_date', '<=', $filtros->fechaHasta);
            })
            ->sum('amount');
    }

    private function calcularPagosPendientes(FiltrosBalanceDTO $filtros): float
    {
        return EnrollmentPayment::where('status', 'pending')
            ->when($filtros->fechaDesde, function ($query) use ($filtros) {
                $query->where('operation_date', '>=', $filtros->fechaDesde);
            })
            ->when($filtros->fechaHasta, function ($query) use ($filtros) {
                $query->where('operation_date', '<=', $filtros->fechaHasta);
            })
            ->sum('amount');
    }

    private function calcularPagosPendientesPorVersion(int $courseVersionId, FiltrosBalanceDTO $filtros): float
    {
        return EnrollmentPayment::where('status', 'pending')
            ->whereHas('enrollment.group', function ($query) use ($courseVersionId) {
                $query->where('course_version_id', $courseVersionId);
            })
            ->when($filtros->fechaDesde, function ($query) use ($filtros) {
                $query->where('operation_date', '>=', $filtros->fechaDesde);
            })
            ->when($filtros->fechaHasta, function ($query) use ($filtros) {
                $query->where('operation_date', '<=', $filtros->fechaHasta);
            })
            ->sum('amount');
    }

    private function calcularIngresosMesActual(FiltrosBalanceDTO $filtros): float
    {
        return EnrollmentPayment::where('status', 'approved')
            ->whereMonth('operation_date', now()->month)
            ->whereYear('operation_date', now()->year)
            ->sum('amount');
    }

    private function calcularGastosNomina(FiltrosBalanceDTO $filtros): float
    {
        return PayrollExpense::when($filtros->fechaDesde, function ($query) use ($filtros) {
                $query->where('date', '>=', $filtros->fechaDesde);
            })
            ->when($filtros->fechaHasta, function ($query) use ($filtros) {
                $query->where('date', '<=', $filtros->fechaHasta);
            })
            ->sum('amount');
    }

    private function calcularPatrimonioNeto(FiltrosBalanceDTO $filtros): float
    {
        $ingresos = $this->calcularIngresosVerificados($filtros);
        $gastos = $this->calcularGastosNomina($filtros);
        
        return $ingresos - $gastos;
    }

    private function contarCursosActivos(FiltrosBalanceDTO $filtros): int
    {
        return CourseVersion::where('status', 'published')->count();
    }

    private function contarMatriculasPagadas(FiltrosBalanceDTO $filtros): int
    {
        return Enrollment::where('payment_status', 'paid')->count();
    }

    private function obtenerMatriculasPorVersion(int $courseVersionId, FiltrosBalanceDTO $filtros): Collection
    {
        return Enrollment::whereHas('group', function ($query) use ($courseVersionId) {
                $query->where('course_version_id', $courseVersionId);
            })
            ->when($filtros->fechaDesde, function ($query) use ($filtros) {
                $query->where('created_at', '>=', $filtros->fechaDesde);
            })
            ->when($filtros->fechaHasta, function ($query) use ($filtros) {
                $query->where('created_at', '<=', $filtros->fechaHasta);
            })
            ->get();
    }

    private function calcularEficienciaCobranza(float $ingresosVerificados, float $pagosPendientes): float
    {
        $total = $ingresosVerificados + $pagosPendientes;
        return $total > 0 ? ($ingresosVerificados / $total) * 100 : 0;
    }

    private function calcularMargenNeto(float $ingresosVerificados, float $gastosNomina): float
    {
        return $ingresosVerificados > 0 ? (($ingresosVerificados - $gastosNomina) / $ingresosVerificados) * 100 : 0;
    }

    private function calcularIngresoPromedioPorCurso(float $ingresosVerificados, int $cursosActivos): float
    {
        return $cursosActivos > 0 ? $ingresosVerificados / $cursosActivos : 0;
    }

    private function calcularTasaRetencion(FiltrosBalanceDTO $filtros): float
    {
        $totalEstudiantes = Enrollment::count();
        $estudiantesActivos = Enrollment::where('academic_status', 'active')->count();
        
        return $totalEstudiantes > 0 ? ($estudiantesActivos / $totalEstudiantes) * 100 : 0;
    }
}