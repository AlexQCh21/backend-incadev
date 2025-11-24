<?php

namespace App\Http\Controllers\RecursosHumanos;

use App\Http\Controllers\Controller;
use App\Services\RecursosHumanos\EmployeeService;
use App\DTOs\RecursosHumanos\EmployeeFiltersDTO;
use App\DTOs\RecursosHumanos\UpdateEmployeeDTO;
use App\DTOs\RecursosHumanos\CreateContractDTO;
use App\DTOs\RecursosHumanos\UpdateContractDTO;
use IncadevUns\CoreDomain\Enums\StaffType;
use IncadevUns\CoreDomain\Enums\StaffPaymentType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeService $employeeService
    ) {}

    /**
     * Display a listing of employees with filters (para la vista RRHH)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('🎯 RRHH Employees index called', $request->all());

            // Validar parámetros de filtro
            $validator = Validator::make($request->all(), [
                'search' => 'sometimes|string',
                'status' => 'sometimes|in:all,active,inactive'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros de filtro inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = EmployeeFiltersDTO::fromRequest($request->all());
            
            // ✅ DEBUG COMPLETO: Contar empleados en la base de datos
            $totalInDatabase = \App\Models\User::whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })->count();

            $activeInDatabase = \App\Models\User::whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })->whereHas('contracts', function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->format('Y-m-d'));
            })->count();

            $inactiveInDatabase = \App\Models\User::whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })->whereDoesntHave('contracts', function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->format('Y-m-d'));
            })->count();

            Log::info("📊 DATABASE COUNTS - Total: {$totalInDatabase}, Active: {$activeInDatabase}, Inactive: {$inactiveInDatabase}");
            Log::info("🔍 Applied filters:", $filters->toArray());

            $employees = $this->employeeService->getEmployees($filters);
            $stats = $this->employeeService->getEmployeesStats();

            // ✅ DEBUG: Contar empleados en la respuesta del servicio
            $activeCount = $employees->filter(fn($emp) => $emp->is_active)->count();
            $inactiveCount = $employees->filter(fn($emp) => !$emp->is_active)->count();
            
            Log::info("📦 SERVICE RESPONSE - Total: {$employees->count()}, Active: {$activeCount}, Inactive: {$inactiveCount}");

            // ✅ DEBUG: Ver algunos empleados de ejemplo
            $sampleEmployees = $employees->take(5)->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->fullname,
                    'is_active' => $employee->is_active,
                    'contracts_count' => count($employee->contracts),
                    'has_active_contract' => $employee->active_contract !== null
                ];
            });
            
            Log::info("👥 Sample employees:", $sampleEmployees->toArray());

            $employeesArray = $employees->map(fn($employee) => $employee->toArray());

            return response()->json([
                'success' => true,
                'employees' => $employeesArray,
                'total_activos' => $stats['total_activos'],
                'total_capacitaciones' => $stats['total_capacitaciones'],
                'filters' => $filters->toArray(),
                'debug' => [
                    'database_counts' => [
                        'total' => $totalInDatabase,
                        'active' => $activeInDatabase,
                        'inactive' => $inactiveInDatabase
                    ],
                    'service_counts' => [
                        'total' => $employees->count(),
                        'active' => $activeCount,
                        'inactive' => $inactiveCount
                    ],
                    'sample_employees' => $sampleEmployees->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error in RRHH Employees index: ' . $e->getMessage());
            Log::error('❌ Error trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar empleados: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all employees (para otros módulos)
     */
    public function getAllEmployees(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getAllEmployees();

            return response()->json([
                'success' => true,
                'data' => $employees->map(fn($employee) => $employee->toArray()),
                'total' => $employees->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getAllEmployees: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar empleados',
            ], 500);
        }
    }

    /**
     * Get active employees only (para otros módulos)
     */
    public function getActiveEmployees(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getActiveEmployees();

            return response()->json([
                'success' => true,
                'data' => $employees->map(fn($employee) => $employee->toArray()),
                'total' => $employees->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getActiveEmployees: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar empleados activos',
            ], 500);
        }
    }

    /**
     * Display the specified employee
     */
    public function show($id): JsonResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'error' => 'Empleado no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $employee->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Employees show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar empleado',
            ], 500);
        }
    }

    /**
     * Update the specified employee
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fullname' => 'required|string',
                'email' => 'required|email',
                'dni' => 'required|string',
                'phone' => 'nullable|string',
                'staff_type' => 'required|string|in:' . implode(',', StaffType::values()),
                'payment_type' => 'required|string|in:' . implode(',', StaffPaymentType::values()),
                'amount' => 'required|numeric|min:0',
                'start_date' => 'required|date',
            ]);

            $dto = UpdateEmployeeDTO::fromRequest($validated);
            $employee = $this->employeeService->updateEmployee($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Empleado actualizado correctamente',
                'data' => $employee->toArray(),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in RRHH Employees update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle employee status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $result = $this->employeeService->toggleEmployeeStatus($id);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'status' => $result['status'],
                'data' => $result['employee']->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Employees toggleStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employees statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->employeeService->getEmployeesStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Employees stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar estadísticas',
            ], 500);
        }
    }

    /**
     * Create new contract for employee
     */
    public function createContract(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'staff_type' => 'required|string|in:' . implode(',', StaffType::values()),
                'payment_type' => 'required|string|in:' . implode(',', StaffPaymentType::values()),
                'amount' => 'required|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date',
            ]);

            $dto = CreateContractDTO::fromRequest($validated);
            $result = $this->employeeService->createNewContract($id, $dto);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'employee' => $result['employee']->toArray(),
                    'contract' => $result['contract']
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in createContract: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate employee (close current contract)
     */
    public function deactivate($id): JsonResponse
    {
        try {
            $result = $this->employeeService->deactivateEmployee($id);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['employee']->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Employees deactivate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate employee with new contract
     */
    public function activate(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'staff_type' => 'required|string|in:' . implode(',', StaffType::values()),
                'payment_type' => 'required|string|in:' . implode(',', StaffPaymentType::values()),
                'amount' => 'required|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date',
            ]);

            $dto = CreateContractDTO::fromRequest($validated);
            $result = $this->employeeService->activateEmployee($id, $dto);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'employee' => $result['employee']->toArray(),
                    'contract' => $result['contract']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in RRHH Employees activate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update contract end date (solo para cerrar contrato)
     */
    public function updateContract(Request $request, $contractId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'end_date' => 'required|date',
            ]);

            $dto = UpdateContractDTO::fromRequest($validated);
            $result = $this->employeeService->updateContract($contractId, $dto);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['employee']->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in updateContract: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}