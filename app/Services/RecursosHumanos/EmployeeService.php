<?php

namespace App\Services\RecursosHumanos;

use App\Repositories\RecursosHumanos\EmployeeRepository;
use App\DTOs\RecursosHumanos\EmployeeDTO;
use App\DTOs\RecursosHumanos\EmployeeFiltersDTO;
use App\DTOs\RecursosHumanos\UpdateEmployeeDTO;
use App\DTOs\RecursosHumanos\CreateContractDTO;
use App\Models\User;
use IncadevUns\CoreDomain\Models\Contract;
use IncadevUns\CoreDomain\Enums\StaffType;
use IncadevUns\CoreDomain\Enums\StaffPaymentType;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class EmployeeService
{
    public function __construct(
        private EmployeeRepository $employeeRepository
    ) {}

    public function getEmployees(EmployeeFiltersDTO $filters): Collection
    {
        $users = $this->employeeRepository->getEmployeesWithFilters($filters);

        return $users->map(function ($user) {
            return $this->formatEmployeeData($user);
        });
    }

    public function getEmployeeById(int $id): ?EmployeeDTO
    {
        $user = $this->employeeRepository->findEmployeeById($id);
        
        if (!$user) {
            return null;
        }

        return $this->formatEmployeeData($user);
    }

    public function updateEmployee(int $id, UpdateEmployeeDTO $dto): EmployeeDTO
    {
        $user = $this->employeeRepository->findEmployeeById($id);

        if (!$user) {
            throw new \Exception('Empleado no encontrado');
        }

        // Actualizar datos básicos del usuario (no del contrato)
        $this->employeeRepository->updateEmployee($user, [
            'fullname' => $dto->fullname,
            'email' => $dto->email,
            'dni' => $dto->dni,
            'phone' => $dto->phone,
        ]);

        $user->refresh();
        return $this->formatEmployeeData($user);
    }

    public function createNewContract(int $employeeId, CreateContractDTO $dto): array
    {
        $user = $this->employeeRepository->findEmployeeById($employeeId);

        if (!$user) {
            throw new \Exception('Empleado no encontrado');
        }

        // Cerrar contrato activo anterior si existe
        $activeContract = $this->getActiveContract($user);
        if ($activeContract) {
            $activeContract->update(['end_date' => Carbon::now()]);
        }

        // Crear nuevo contrato
        $newContract = $user->contracts()->create([
            'staff_type' => $dto->staff_type,
            'payment_type' => $dto->payment_type,
            'amount' => $dto->amount,
            'start_date' => $dto->start_date,
            'end_date' => $dto->end_date,
        ]);

        $user->refresh();
        $updatedEmployee = $this->formatEmployeeData($user);

        return [
            'message' => 'Nuevo contrato creado correctamente',
            'contract' => $newContract,
            'employee' => $updatedEmployee
        ];
    }

    public function deactivateEmployee(int $id): array
    {
        $user = $this->employeeRepository->findEmployeeById($id);

        if (!$user) {
            throw new \Exception('Empleado no encontrado');
        }

        $activeContract = $this->getActiveContract($user);

        if (!$activeContract) {
            throw new \Exception('El empleado ya está inactivo');
        }

        // Desactivar: establecer fecha de fin (hoy) en el contrato actual
        $activeContract->update(['end_date' => Carbon::now()]);
        
        $user->refresh();
        $updatedEmployee = $this->formatEmployeeData($user);

        return [
            'message' => 'Empleado dado de baja correctamente',
            'employee' => $updatedEmployee
        ];
    }

    public function activateEmployee(int $id, CreateContractDTO $dto): array
    {
        $user = $this->employeeRepository->findEmployeeById($id);

        if (!$user) {
            throw new \Exception('Empleado no encontrado');
        }

        // Verificar que no tenga contrato activo
        $activeContract = $this->getActiveContract($user);
        if ($activeContract) {
            throw new \Exception('El empleado ya tiene un contrato activo');
        }

        // Crear nuevo contrato para la reactivación
        $newContract = $user->contracts()->create([
            'staff_type' => $dto->staff_type,
            'payment_type' => $dto->payment_type,
            'amount' => $dto->amount,
            'start_date' => $dto->start_date,
            'end_date' => $dto->end_date,
        ]);

        $user->refresh();
        $updatedEmployee = $this->formatEmployeeData($user);

        return [
            'message' => 'Empleado reactivado con nuevo contrato',
            'contract' => $newContract,
            'employee' => $updatedEmployee
        ];
    }

    public function getEmployeesStats(): array
    {
        $filters = new EmployeeFiltersDTO(status: 'all');
        
        try {
            // Para consistencia, usar la misma lógica que getEmployees
            $allEmployees = $this->getEmployees($filters);
            $total_activos = $allEmployees->filter(fn($emp) => $emp->is_active)->count();
            
            \Log::info("📊 Stats calculation - Total employees: {$allEmployees->count()}, Active: {$total_activos}");
            
            return [
                'total_activos' => $total_activos,
                'total_capacitaciones' => $this->getTrainingCount(),
            ];
        } catch (\Exception $e) {
            \Log::error('Error calculating stats: ' . $e->getMessage());
            // Fallback al repositorio si hay error
            return [
                'total_activos' => $this->employeeRepository->getActiveEmployeesCount(),
                'total_capacitaciones' => $this->getTrainingCount(),
            ];
        }
    }

    private function getActiveContract(User $user): ?Contract
    {
        $today = Carbon::now()->format('Y-m-d');
        
        return $user->contracts
            ->where(function ($contract) use ($today) {
                return $contract->end_date === null || 
                    $contract->end_date->format('Y-m-d') > $today; // Cambiar >= por >
            })
            ->first();
    }

    private function formatEmployeeData(User $user): EmployeeDTO
    {
        // Filtro de roles para empleados - EXCLUIR admin, student y viewer
        $employeeRoles = $user->roles->filter(function ($role) {
            return !in_array($role->name, ['admin', 'student', 'viewer']);
        })->pluck('name');

        $activeContract = $this->getActiveContract($user);
        $lastContract = $user->contracts->sortByDesc('start_date')->first();

        $contracts = $user->contracts->map(function ($contract) {
            return [
                'id' => $contract->id,
                'staff_type' => $contract->staff_type?->value,
                'payment_type' => $contract->payment_type?->value,
                'amount' => (float) $contract->amount,
                'start_date' => $contract->start_date->format('Y-m-d'),
                'end_date' => $contract->end_date?->format('Y-m-d'),
                'is_active' => $this->isContractActive($contract),
            ];
        })->toArray();

        $activeContractData = $activeContract ? [
            'id' => $activeContract->id,
            'staff_type' => $activeContract->staff_type?->value,
            'payment_type' => $activeContract->payment_type?->value,
            'amount' => (float) $activeContract->amount,
            'start_date' => $activeContract->start_date->format('Y-m-d'),
            'end_date' => $activeContract->end_date?->format('Y-m-d'),
        ] : null;

        $lastContractData = $lastContract ? [
            'id' => $lastContract->id,
            'staff_type' => $lastContract->staff_type?->value,
            'payment_type' => $lastContract->payment_type?->value,
            'amount' => (float) $lastContract->amount,
            'start_date' => $lastContract->start_date->format('Y-m-d'),
            'end_date' => $lastContract->end_date?->format('Y-m-d'),
        ] : null;

        return EmployeeDTO::fromArray([
            'id' => $user->id,
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'fullname' => $user->fullname ?? '',
            'dni' => $user->dni,
            'phone' => $user->phone,
            'roles' => $employeeRoles->toArray(),
            'contracts' => $contracts,
            'is_active' => $activeContract !== null,
            'active_contract' => $activeContractData,
            'last_contract' => $lastContractData,
            'created_at' => $user->created_at->toISOString(),
        ]);
    }

    private function isContractActive(Contract $contract): bool
    {
        $today = Carbon::now()->format('Y-m-d');
        return $contract->end_date === null || 
            $contract->end_date->format('Y-m-d') > $today; // Cambiar >= por >
    }

    private function getTrainingCount(): int
    {
        return 12; // Mock data
    }
}