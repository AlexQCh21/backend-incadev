<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Services\RecursosHumanos\PayrollService;
use App\DTOs\RecursosHumanos\CreatePayrollExpenseDTO;
use App\DTOs\RecursosHumanos\PayrollFiltersDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollService $payrollService
    ) {}

    /**
     * Display a listing of payroll expenses
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validar parámetros de filtro
            $validator = Validator::make($request->all(), [
                'employee_id' => 'sometimes|integer',
                'contract_id' => 'sometimes|integer',
                'sort_by' => 'sometimes|string|in:date_desc,date_asc,amount_desc,amount_asc', // ✅ NUEVO
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros de filtro inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = PayrollFiltersDTO::fromRequest($request->all());
            Log::info('📊 Payroll index filters:', $filters->toArray());
            
            $expenses = $this->payrollService->getPayrollExpenses($filters);

            return response()->json([
                'success' => true,
                'data' => $expenses,
                'total' => $expenses->count(),
                'filters' => $filters->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in Payroll index: ' . $e->getMessage());
            Log::error('❌ Error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar los registros de nómina',
            ], 500);
        }
    }

    /**
     * Store a newly created payroll expense
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'contract_id' => 'required|exists:contracts,id',
                'amount' => 'required|numeric|min:0',
                'date' => 'required|date',
                'description' => 'nullable|string|max:500',
            ]);

            $dto = CreatePayrollExpenseDTO::fromRequest($validated);
            $payrollExpense = $this->payrollService->createPayrollExpense($dto);

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado correctamente',
                'data' => $payrollExpense->load(['contract.user'])
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Error in Payroll store: ' . $e->getMessage());
            Log::error('❌ Error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display payroll statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            // Validar parámetros de filtro
            $validator = Validator::make($request->all(), [
                'employee_id' => 'sometimes|integer',
                'contract_id' => 'sometimes|integer',
                'sort_by' => 'sometimes|string|in:date_desc,date_asc,amount_desc,amount_asc', // ✅ NUEVO
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros de filtro inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = PayrollFiltersDTO::fromRequest($request->all());
            Log::info('📈 Payroll stats filters:', $filters->toArray());
            
            $stats = $this->payrollService->getPayrollStats($filters);
            Log::info('📈 Payroll stats result:', $stats);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in Payroll stats: ' . $e->getMessage());
            Log::error('❌ Error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar estadísticas de nómina: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payroll history for specific employee
     */
    public function employeeHistory($employeeId): JsonResponse
    {
        try {
            $filters = new PayrollFiltersDTO(employee_id: $employeeId);
            $history = $this->payrollService->getPayrollExpenses($filters);

            return response()->json([
                'success' => true,
                'data' => $history,
                'total' => $history->count()
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in Payroll employeeHistory: ' . $e->getMessage());
            Log::error('❌ Error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar historial de pagos',
            ], 500);
        }
    }

    /**
     * Update the specified payroll expense
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'sometimes|numeric|min:0',
                'date' => 'sometimes|date',
                'description' => 'nullable|string|max:500',
            ]);

            $payrollExpense = $this->payrollService->updatePayrollExpense($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Registro de pago actualizado correctamente',
                'data' => $payrollExpense
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in Payroll update: ' . $e->getMessage());
            Log::error('❌ Error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified payroll expense
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->payrollService->deletePayrollExpense($id);

            return response()->json([
                'success' => true,
                'message' => 'Registro de pago eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in Payroll destroy: ' . $e->getMessage());
            Log::error('❌ Error trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}