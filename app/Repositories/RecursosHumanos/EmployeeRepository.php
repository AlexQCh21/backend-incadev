<?php

namespace App\Repositories\RecursosHumanos;

use App\Models\User;
use App\DTOs\RecursosHumanos\EmployeeFiltersDTO;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class EmployeeRepository
{
    public function getEmployeesWithFilters(EmployeeFiltersDTO $filters): Collection
    {
        // Consulta base para todos los empleados - EXCLUIR admin, student y viewer
        $query = User::with(['roles', 'contracts'])
            ->whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            });

        // ✅ DEBUG: Contar antes de aplicar filtros
        $baseCount = $query->count();
        \Log::info("📋 Repository base query count: {$baseCount}");

        // Aplicar filtro de búsqueda (si existe) - BÚSQUEDA CASE INSENSITIVE
        if ($filters->search) {
            $searchTerm = strtolower($filters->search);
            $query->where(function ($q) use ($searchTerm, $filters) { // ← CORREGIDO: agregar $filters al use
                $q->whereRaw('LOWER(fullname) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhere('dni', 'like', "%{$filters->search}%") // ← AHORA $filters está disponible
                  ->orWhereHas('roles', function ($roleQuery) use ($searchTerm) {
                      $roleQuery->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                  });
            });
            
            $afterSearchCount = $query->count();
            \Log::info("🔍 After search filter: {$afterSearchCount}");
        }

        // Solo aplicar filtro de estado si no es 'all'
        if ($filters->status !== 'all') {
            $today = Carbon::now()->format('Y-m-d');
            
            if ($filters->status === 'active') {
                // Empleados activos
                $query->whereHas('contracts', function ($contractQuery) use ($today) {
                    $contractQuery->where(function ($q) use ($today) {
                        $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                    });
                });
            } elseif ($filters->status === 'inactive') {
                // Empleados inactivos
                $query->whereDoesntHave('contracts', function ($contractQuery) use ($today) {
                    $contractQuery->where(function ($q) use ($today) {
                        $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                    });
                });
            }
            
            $afterStatusCount = $query->count();
            \Log::info("🎯 After status filter '{$filters->status}': {$afterStatusCount}");
        }

        $result = $query->orderBy('fullname', 'asc')->get();
        
        // ✅ DEBUG: Verificar contratos de los resultados
        $result->each(function ($user) {
            $activeContracts = $user->contracts->filter(function ($contract) {
                $today = Carbon::now()->format('Y-m-d');
                return $contract->end_date === null || $contract->end_date->format('Y-m-d') >= $today;
            });
            
            \Log::info("📄 User {$user->id} - {$user->fullname}: Contracts: {$user->contracts->count()}, Active: {$activeContracts->count()}");
        });

        \Log::info("📤 Repository final result count: {$result->count()}");

        return $result;
    }

    public function getAllEmployees(): Collection
    {
        return User::with(['roles', 'contracts'])
            ->whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })
            ->orderBy('fullname', 'asc')
            ->get();
    }

    public function getActiveEmployees(): Collection
    {
        $today = Carbon::now()->format('Y-m-d');
        
        return User::with(['roles', 'contracts'])
            ->whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })
            ->whereHas('contracts', function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>', $today);
                });
            })
            ->orderBy('fullname', 'asc')
            ->get();
    }

    public function getInactiveEmployees(): Collection
    {
        $today = Carbon::now()->format('Y-m-d');
        
        return User::with(['roles', 'contracts'])
            ->whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })
            ->whereDoesntHave('contracts', function ($contractQuery) use ($today) {
                $contractQuery->where(function ($q) use ($today) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>', $today);
                });
            })
            ->orderBy('fullname', 'asc')
            ->get();
    }

    public function findEmployeeById(int $id): ?User
    {
        return User::with(['roles', 'contracts'])
            ->whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })
            ->find($id);
    }

    public function updateEmployee(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh(['roles', 'contracts']);
    }

    public function getActiveEmployeesCount(): int
    {
        $today = Carbon::now()->format('Y-m-d');
        
        return User::whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })
            ->whereHas('contracts', function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $today);
                });
            })
            ->count();
    }

    public function getInactiveEmployeesCount(): int
    {
        $today = Carbon::now()->format('Y-m-d');
        
        return User::whereHas('roles', function ($query) {
                $query->whereNotIn('name', ['admin', 'student', 'viewer']);
            })
            ->whereDoesntHave('contracts', function ($contractQuery) use ($today) {
                $contractQuery->where(function ($q) use ($today) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $today);
                });
            })
            ->count();
    }

    public function getEmployeesByRole(string $role): Collection
    {
        return User::with(['roles', 'contracts'])
            ->whereHas('roles', function ($query) use ($role) {
                $query->where('name', $role)
                      ->whereNotIn('name', ['admin', 'student', 'viewer']);
            })
            ->orderBy('fullname', 'asc')
            ->get();
    }
}