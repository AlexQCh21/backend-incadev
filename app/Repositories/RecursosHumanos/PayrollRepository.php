<?php

namespace App\Repositories\RecursosHumanos;

use App\DTOs\RecursosHumanos\PayrollFiltersDTO;
use IncadevUns\CoreDomain\Models\PayrollExpense;
use Illuminate\Support\Collection;

class PayrollRepository
{
    public function getPayrollExpensesWithFilters(PayrollFiltersDTO $filters): Collection
    {
        $query = PayrollExpense::with(['contract.user']);

        if ($filters->employee_id) {
            $query->whereHas('contract', function ($q) use ($filters) {
                $q->where('user_id', $filters->employee_id);
            });
        }

        if ($filters->contract_id) {
            $query->where('contract_id', $filters->contract_id);
        }

        // ✅ ORDENAMIENTO SEGÚN EL FILTRO
        if ($filters->sort_by === 'amount_desc') {
            $query->orderBy('amount', 'desc');
        } else if ($filters->sort_by === 'amount_asc') {
            $query->orderBy('amount', 'asc');
        } else if ($filters->sort_by === 'date_asc') {
            $query->orderBy('date', 'asc');
        } else {
            // Por defecto: más recientes primero
            $query->orderBy('date', 'desc');
        }

        // Orden secundario por ID para consistencia
        $query->orderBy('id', 'desc');

        return $query->get();
    }

    public function findPayrollExpenseById(int $id): ?PayrollExpense
    {
        return PayrollExpense::with(['contract.user'])->find($id);
    }

    public function createPayrollExpense(array $data): PayrollExpense
    {
        return PayrollExpense::create($data);
    }

    public function updatePayrollExpense(PayrollExpense $payrollExpense, array $data): PayrollExpense
    {
        $payrollExpense->update($data);
        return $payrollExpense->fresh(['contract.user']);
    }

    public function deletePayrollExpense(PayrollExpense $payrollExpense): bool
    {
        return $payrollExpense->delete();
    }

    public function getTotalPayrollByPeriod(string $startDate, string $endDate): float
    {
        return PayrollExpense::whereBetween('date', [$startDate, $endDate])
                            ->sum('amount');
    }

    public function getEmployeePayrollHistory(int $employeeId): Collection
    {
        return PayrollExpense::with(['contract'])
            ->whereHas('contract', function ($q) use ($employeeId) {
                $q->where('user_id', $employeeId);
            })
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getPayrollStatsWithFilters(PayrollFiltersDTO $filters): array
    {
        $query = PayrollExpense::with(['contract']);

        if ($filters->employee_id) {
            $query->whereHas('contract', function ($q) use ($filters) {
                $q->where('user_id', $filters->employee_id);
            });
        }

        if ($filters->contract_id) {
            $query->where('contract_id', $filters->contract_id);
        }

        $expenses = $query->get();

        $totalAmount = $expenses->sum('amount');
        $totalPayments = $expenses->count();
        
        // Agrupar por tipo de staff
        $amountByStaffType = $expenses->groupBy(function ($expense) {
            return $expense->contract->staff_type?->value ?? 'unknown';
        })->map(function ($group) {
            return $group->sum('amount');
        });

        // Agrupar por tipo de pago
        $amountByPaymentType = $expenses->groupBy(function ($expense) {
            return $expense->contract->payment_type?->value ?? 'unknown';
        })->map(function ($group) {
            return $group->sum('amount');
        });

        return [
            'total_amount' => $totalAmount,
            'total_payments' => $totalPayments,
            'amount_by_staff_type' => $amountByStaffType->toArray(),
            'amount_by_payment_type' => $amountByPaymentType->toArray(),
            'average_payment' => $totalPayments > 0 ? $totalAmount / $totalPayments : 0,
        ];
    }
}