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
    // ⚠️ TEMPORAL PARA TESTING - Simular que estamos en Noviembre 2025
    private function getCurrentDate()
    {
        // Cambia esto a true para testing, false para producción
        $testing = true;

        if ($testing) {
            return Carbon::create(2025, 11, 15); // 15 de Noviembre 2025
        }

        return Carbon::now();
    }

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
     */
    private function updateSatisfaccionEstudiantil(KpiGoal $kpi): void
    {
        $surveyId = DB::table('surveys')
            ->where('title', 'like', '%Satisfacción Estudiantil%')
            ->value('id');

        if (!$surveyId) {
            Log::warning('No se encontró encuesta de Satisfacción Estudiantil');
            return;
        }

        $currentDate = $this->getCurrentDate();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

        // Mes actual
        $currentAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $currentMonth)
            ->whereYear('survey_responses.date', $currentYear)
            ->avg('response_details.score');

        // Mes anterior
        $previousDate = $currentDate->copy()->subMonth();
        $previousAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $previousDate->month)
            ->whereYear('survey_responses.date', $previousDate->year)
            ->avg('response_details.score');

        // Convertir de escala 1-5 a porcentaje
        $currentValue = $currentAvg ? ($currentAvg / 5) * 100 : 0;
        $previousValue = $previousAvg ? ($previousAvg / 5) * 100 : 0;

        // Calcular tendencia
        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        Log::info('KPI Satisfacción Estudiantil', [
            'survey_id' => $surveyId,
            'current_avg' => $currentAvg,
            'previous_avg' => $previousAvg,
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'trend' => $trend,
        ]);

        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * KPI 2: Ejecución Presupuestal
     */
    private function updateEjecucionPresupuestal(KpiGoal $kpi): void
    {
        $currentDate = $this->getCurrentDate();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

        $currentIngresos = DB::table('enrollment_payments')
            ->where('status', 'approved')
            ->whereYear('operation_date', $currentYear)
            ->whereMonth('operation_date', $currentMonth)
            ->sum('amount');

        $currentGastos = DB::table('payroll_expenses')
            ->whereYear('date', $currentYear)
            ->whereMonth('date', $currentMonth)
            ->sum('amount');

        $currentTotal = $currentIngresos + $currentGastos;
        $currentValue = $currentTotal > 0 ? ($currentIngresos / $currentTotal) * 100 : 0;

        // Mes anterior
        $previousDate = $currentDate->copy()->subMonth();
        $previousIngresos = DB::table('enrollment_payments')
            ->where('status', 'approved')
            ->whereYear('operation_date', $previousDate->year)
            ->whereMonth('operation_date', $previousDate->month)
            ->sum('amount');

        $previousGastos = DB::table('payroll_expenses')
            ->whereYear('date', $previousDate->year)
            ->whereMonth('date', $previousDate->month)
            ->sum('amount');

        $previousTotal = $previousIngresos + $previousGastos;
        $previousValue = $previousTotal > 0 ? ($previousIngresos / $previousTotal) * 100 : 0;

        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        Log::info('KPI Ejecución Presupuestal', [
            'ingresos' => $currentIngresos,
            'gastos' => $currentGastos,
            'total' => $currentTotal,
            'porcentaje' => $currentValue,
        ]);

        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * KPI 3: Satisfacción con Instructores
     */
    private function updateSatisfaccionInstructores(KpiGoal $kpi): void
    {
        $surveyId = DB::table('surveys')
            ->where('title', 'like', '%Seguimiento del Docente%')
            ->value('id');

        if (!$surveyId) {
            Log::warning('No se encontró encuesta de Seguimiento del Docente');
            return;
        }

        $currentDate = $this->getCurrentDate();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

        $currentAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $currentMonth)
            ->whereYear('survey_responses.date', $currentYear)
            ->avg('response_details.score');

        $previousDate = $currentDate->copy()->subMonth();
        $previousAvg = DB::table('survey_responses')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $previousDate->month)
            ->whereYear('survey_responses.date', $previousDate->year)
            ->avg('response_details.score');

        $currentValue = $currentAvg ? ($currentAvg / 5) * 100 : 0;
        $previousValue = $previousAvg ? ($previousAvg / 5) * 100 : 0;

        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        Log::info('KPI Satisfacción Instructores', [
            'survey_id' => $surveyId,
            'current_avg' => $currentAvg,
            'previous_avg' => $previousAvg,
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'trend' => $trend,
        ]);

        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * KPI 4: Tasa de Empleabilidad de Graduados
     */
    private function updateEmpleabilidadGraduados(KpiGoal $kpi): void
    {
        $surveyId = DB::table('surveys')
            ->where('title', 'like', '%Seguimiento del Egresado%')
            ->value('id');

        if (!$surveyId) {
            Log::warning('No se encontró encuesta de Seguimiento del Egresado');
            return;
        }

        $currentDate = $this->getCurrentDate();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

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

        $previousDate = $currentDate->copy()->subMonth();
        $previousResponses = DB::table('survey_responses')
            ->select('survey_responses.id')
            ->join('response_details', 'survey_responses.id', '=', 'response_details.survey_response_id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereMonth('survey_responses.date', $previousDate->month)
            ->whereYear('survey_responses.date', $previousDate->year)
            ->groupBy('survey_responses.id')
            ->havingRaw('AVG(response_details.score) >= 4')
            ->count();

        $totalPreviousResponses = DB::table('survey_responses')
            ->where('survey_id', $surveyId)
            ->whereMonth('date', $previousDate->month)
            ->whereYear('date', $previousDate->year)
            ->count();

        $previousValue = $totalPreviousResponses > 0
            ? ($previousResponses / $totalPreviousResponses) * 100
            : 0;

        $trend = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        Log::info('KPI Empleabilidad', [
            'survey_id' => $surveyId,
            'current_responses' => $currentResponses,
            'total_current' => $totalCurrentResponses,
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'trend' => $trend,
        ]);

        $kpi->previous_value = round($previousValue, 2);
        $kpi->current_value = round($currentValue, 2);
        $kpi->trend = round($trend, 2);
        $kpi->status = $this->calculateStatus($currentValue, $kpi->goal_value);
        $kpi->save();
    }

    /**
     * Calcular el estado del KPI
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
