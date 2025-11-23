<?php

namespace App\Http\Controllers\Indicators;

use App\Http\Controllers\Controller;
use App\Models\KpiGoal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KpiController extends Controller
{
    /**
     * Obtener todos los KPIs con sus valores calculados
     */
    public function index(): JsonResponse
    {
        try {
            // Actualizar todos los KPIs antes de mostrarlos
            $this->updateAllKpis();

            $kpis = KpiGoal::orderBy('id')->get();

            return response()->json([
                'success' => true,
                'data' => $kpis,
                'last_updated' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener KPIs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los indicadores'
            ], 500);
        }
    }

    /**
     * Actualizar la meta de un KPI específico
     */
    public function updateGoal(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'goal_value' => 'required|numeric|min:0|max:100',
            ]);

            $kpi = KpiGoal::findOrFail($id);
            $kpi->goal_value = $validated['goal_value'];

            // Recalcular estado con la nueva meta
            $kpi->status = $this->calculateStatus($kpi->current_value, $validated['goal_value']);
            $kpi->save();

            return response()->json([
                'success' => true,
                'message' => 'Meta actualizada exitosamente',
                'data' => $kpi
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar meta del KPI', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar la meta'
            ], 500);
        }
    }

    /**
     * Forzar recálculo de todos los KPIs
     */
    public function recalculate(): JsonResponse
    {
        try {
            $this->updateAllKpis();

            return response()->json([
                'success' => true,
                'message' => 'KPIs recalculados exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al recalcular KPIs', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Error al recalcular los indicadores'
            ], 500);
        }
    }

    /**
     * Actualizar todos los KPIs
     */
    private function updateAllKpis(): void
    {
        $kpis = KpiGoal::all();

        foreach ($kpis as $kpi) {
            switch ($kpi->name) {
                case 'satisfaccion_estudiantil':
                    $this->updateSatisfaccionEstudiantil($kpi);
                    break;
                case 'ejecucion_presupuestal':
                    $this->updateEjecucionPresupuestal($kpi);
                    break;
                case 'satisfaccion_instructores':
                    $this->updateSatisfaccionInstructores($kpi);
                    break;
                case 'empleabilidad_graduados':
                    $this->updateEmpleabilidadGraduados($kpi);
                    break;
            }
        }
    }

    /**
     * KPI 1: Satisfacción Estudiantil
     * Calcula el promedio de las respuestas de la encuesta de satisfacción estudiantil
     */
    private function updateSatisfaccionEstudiantil(KpiGoal $kpi): void
    {
        // ID de la encuesta de satisfacción estudiantil (ajusta según tu BD)
        $surveyId = DB::table('surveys')
            ->where('title', 'like', '%Satisfacción Estudiantil%')
            ->value('id');

        if (!$surveyId) {
            return;
        }

        // Calcular promedio del mes actual
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $currentAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $currentMonth)
            ->whereYear('survey_responses.date', $currentYear)
            ->avg('response_details.score');

        // Calcular promedio del mes anterior
        $previousMonth = Carbon::now()->subMonth();
        $previousAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $previousMonth->month)
            ->whereYear('survey_responses.date', $previousMonth->year)
            ->avg('response_details.score');

        // Convertir de escala 1-5 a porcentaje (0-100%)
        $currentValue = $currentAvg ? ($currentAvg / 5) * 100 : 0;
        $previousValue = $previousAvg ? ($previousAvg / 5) * 100 : 0;

        // Calcular tendencia
        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        // Actualizar KPI
        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * KPI 2: Ejecución Presupuestal
     * Calcula: (Ingresos / (Ingresos + Gastos)) * 100
     */
    private function updateEjecucionPresupuestal(KpiGoal $kpi): void
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Ingresos del mes actual (enrollment_payments aprobados)
        $currentIngresos = DB::table('enrollment_payments')
            ->where('status', 'approved')
            ->whereYear('operation_date', $currentYear)
            ->whereMonth('operation_date', $currentMonth)
            ->sum('amount');

        // Gastos del mes actual (payroll_expenses)
        $currentGastos = DB::table('payroll_expenses')
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->sum('amount');

        // Calcular porcentaje de ejecución presupuestal
        $currentTotal = $currentIngresos + $currentGastos;
        $currentValue = $currentTotal > 0 ? ($currentIngresos / $currentTotal) * 100 : 0;

        // Mes anterior (acumulado hasta el mes anterior)
        $previousMonth = Carbon::now()->subMonth();
        $previousIngresos = DB::table('enrollment_payments')
            ->where('status', 'approved')
            ->whereYear('operation_date', $previousMonth->year)
            ->where(function($query) use ($previousMonth) {
                $query->whereMonth('operation_date', '<', $previousMonth->month)
                    ->orWhere(function($q) use ($previousMonth) {
                        $q->whereMonth('operation_date', '=', $previousMonth->month)
                            ->whereYear('operation_date', '=', $previousMonth->year);
                    });
            })
            ->sum('amount');

        $previousGastos = DB::table('payroll_expenses')
            ->whereYear('date', $previousMonth->year)
            ->where(function($query) use ($previousMonth) {
                $query->whereMonth('date', '<', $previousMonth->month)
                    ->orWhere(function($q) use ($previousMonth) {
                        $q->whereMonth('date', '=', $previousMonth->month)
                            ->whereYear('date', '=', $previousMonth->year);
                    });
            })
            ->sum('amount');

        $previousTotal = $previousIngresos + $previousGastos;
        $previousValue = $previousTotal > 0 ? ($previousIngresos / $previousTotal) * 100 : 0;

        // Calcular tendencia
        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        // Log para debugging
        Log::info('Ejecución Presupuestal', [
            'ingresos' => $currentIngresos,
            'gastos' => $currentGastos,
            'total' => $currentTotal,
            'porcentaje' => $currentValue,
        ]);

        // Actualizar KPI
        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * KPI 3: Satisfacción con Instructores
     * Calcula el promedio de las respuestas de la encuesta de seguimiento del docente
     */
    private function updateSatisfaccionInstructores(KpiGoal $kpi): void
    {
        // ID de la encuesta de seguimiento del docente
        $surveyId = DB::table('surveys')
            ->where('title', 'like', '%Seguimiento del Docente%')
            ->value('id');

        if (!$surveyId) {
            return;
        }

        // Calcular promedio del mes actual
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $currentAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $currentMonth)
            ->whereYear('survey_responses.date', $currentYear)
            ->avg('response_details.score');

        // Calcular promedio del mes anterior
        $previousMonth = Carbon::now()->subMonth();
        $previousAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $previousMonth->month)
            ->whereYear('survey_responses.date', $previousMonth->year)
            ->avg('response_details.score');

        // Convertir de escala 1-5 a porcentaje (0-100%)
        $currentValue = $currentAvg ? ($currentAvg / 5) * 100 : 0;
        $previousValue = $previousAvg ? ($previousAvg / 5) * 100 : 0;

        // Calcular tendencia
        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        // Actualizar KPI
        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * KPI 4: Tasa de Empleabilidad de Graduados
     * Calcula el porcentaje de graduados empleados basado en la encuesta de seguimiento
     */
    private function updateEmpleabilidadGraduados(KpiGoal $kpi): void
    {
        // ID de la encuesta de seguimiento del egresado
        $surveyId = DB::table('surveys')
            ->where('title', 'like', '%Seguimiento del Egresado%')
            ->value('id');

        if (!$surveyId) {
            return;
        }

        // Mes actual
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Contar respuestas con promedio >= 4 (considerados "empleados exitosamente")
        $currentResponses = DB::table('survey_responses')
            ->select('survey_responses.id')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $currentMonth)
            ->whereYear('survey_responses.date', $currentYear)
            ->groupBy('survey_responses.id')
            ->havingRaw('AVG(response_details.score) >= 4')
            ->count();

        $totalCurrentResponses = DB::table('survey_responses')
            ->where('survey_id', $surveyId)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->count();

        $currentValue = $totalCurrentResponses > 0
            ? ($currentResponses / $totalCurrentResponses) * 100
            : 0;

        // Mes anterior
        $previousMonth = Carbon::now()->subMonth();
        $previousResponses = DB::table('survey_responses')
            ->select('survey_responses.id')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $previousMonth->month)
            ->whereYear('survey_responses.date', $previousMonth->year)
            ->groupBy('survey_responses.id')
            ->havingRaw('AVG(response_details.score) >= 4')
            ->count();

        $totalPreviousResponses = DB::table('survey_responses')
            ->where('survey_id', $surveyId)
            ->whereMonth('date', $previousMonth->month)
            ->whereYear('date', $previousMonth->year)
            ->count();

        $previousValue = $totalPreviousResponses > 0
            ? ($previousResponses / $totalPreviousResponses) * 100
            : 0;

        // Calcular tendencia
        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        // Actualizar KPI
        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * Calcular el estado del KPI basado en el valor actual y la meta
     */
    private function calculateStatus(float $currentValue, float $goalValue): string
    {
        $percentage = $goalValue > 0 ? ($currentValue / $goalValue) * 100 : 0;

        if ($percentage >= 95) {
            return 'Cumplido';
        } elseif ($percentage >= 70) {
            return 'En camino';
        } else {
            return 'Requiere atención';
        }
    }

    // En KpiController.php

    /**
     * Obtener datos para exportación PDF
     */
    public function exportData(): JsonResponse
    {
        try {
            $this->updateAllKpis();
            $kpis = KpiGoal::orderBy('id')->get();

            return response()->json([
                'success' => true,
                'kpis' => $kpis,
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al exportar datos', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Error al exportar datos'
            ], 500);
        }
    }
}
