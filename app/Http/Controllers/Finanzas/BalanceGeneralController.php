<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Services\Finanzas\BalanceGeneralService;
use App\DTOs\Finanzas\BalanceGeneralResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceGeneralController extends Controller
{
    public function __construct(
        private BalanceGeneralService $balanceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $this->validarFiltros($request);
            $balance = $this->balanceService->obtenerBalanceGeneral($filtros);

            // Pasar el objeto DTO directamente al Resource
            return response()->json([
                'success' => true,
                'data' => new BalanceGeneralResource($balance), // ← Sin ->toArray()
                'message' => 'Balance general obtenido exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el balance general',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function validarFiltros(Request $request): array
    {
        return $request->validate([
            'fecha_desde' => 'sometimes|date|before_or_equal:fecha_hasta',
            'fecha_hasta' => 'sometimes|date|after_or_equal:fecha_desde',
            'curso_id' => 'sometimes|integer|exists:courses,id',
            'version_id' => 'sometimes|integer|exists:course_versions,id'
        ]);
    }
}