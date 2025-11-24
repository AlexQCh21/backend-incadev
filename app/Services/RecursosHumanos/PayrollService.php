<?php

namespace App\Services\RecursosHumanos;

use App\Repositories\RecursosHumanos\PayrollRepository;
use App\DTOs\RecursosHumanos\CreatePayrollExpenseDTO;
use App\DTOs\RecursosHumanos\PayrollFiltersDTO;
use IncadevUns\CoreDomain\Models\PayrollExpense;
use IncadevUns\CoreDomain\Models\Contract;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PayrollService
{
    public function __construct(
        private PayrollRepository $payrollRepository
    ) {}

    public function getPayrollExpenses(PayrollFiltersDTO $filters): Collection
    {
        return $this->payrollRepository->getPayrollExpensesWithFilters($filters);
    }

    public function getPayrollExpenseById(int $id): ?PayrollExpense
    {
        return $this->payrollRepository->findPayrollExpenseById($id);
    }

    public function createPayrollExpense(CreatePayrollExpenseDTO $dto): PayrollExpense
    {
        // Verificar que el contrato existe y está activo
        $contract = Contract::with('user')->find($dto->contract_id);
        
        if (!$contract) {
            throw new \Exception('Contrato no encontrado');
        }

        // Verificar que la fecha del pago es válida (no futura)
        $paymentDate = Carbon::parse($dto->date);
        if ($paymentDate->isFuture()) {
            throw new \Exception('La fecha del pago no puede ser futura');
        }

        // Verificar que no existe un pago para el mismo contrato en el mismo mes
        $startOfMonth = $paymentDate->copy()->startOfMonth();
        $endOfMonth = $paymentDate->copy()->endOfMonth();
        
        $existingPayment = PayrollExpense::where('contract_id', $dto->contract_id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->first();

        if ($existingPayment) {
            throw new \Exception('Ya existe un pago registrado para este contrato en el mes seleccionado');
        }

        return $this->payrollRepository->createPayrollExpense($dto->toArray());
    }

    public function updatePayrollExpense(int $id, array $data): PayrollExpense
    {
        $payrollExpense = $this->payrollRepository->findPayrollExpenseById($id);
        
        if (!$payrollExpense) {
            throw new \Exception('Registro de pago no encontrado');
        }

        return $this->payrollRepository->updatePayrollExpense($payrollExpense, $data);
    }

    public function deletePayrollExpense(int $id): bool
    {
        $payrollExpense = $this->payrollRepository->findPayrollExpenseById($id);
        
        if (!$payrollExpense) {
            throw new \Exception('Registro de pago no encontrado');
        }

        return $this->payrollRepository->deletePayrollExpense($payrollExpense);
    }

    public function getPayrollStats(PayrollFiltersDTO $filters): array
    {
        return $this->payrollRepository->getPayrollStatsWithFilters($filters);
    }
}