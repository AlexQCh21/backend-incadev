<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollExpensesSeeder extends Seeder
{
    public function run(): void
    {
        // Desactivar temporalmente las claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Limpiar tablas y reiniciar IDs
        DB::table('payroll_expenses')->truncate();
        DB::table('contracts')->truncate();

        // Reactivar claves foráneas
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ======================================================
        // 1. Crear contratos de prueba
        // ======================================================
        $contractIds = [];
        $contractIds[] = DB::table('contracts')->insertGetId([
            'user_id'      => 2, // evita el usuario 1 según tu preferencia
            'staff_type'   => 'docente',
            'payment_type' => 'mensual',
            'amount'       => 3000,
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-12-31',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $contractIds[] = DB::table('contracts')->insertGetId([
            'user_id'      => 3,
            'staff_type'   => 'administrativo',
            'payment_type' => 'mensual',
            'amount'       => 2500,
            'start_date'   => '2025-01-01',
            'end_date'     => '2025-12-31',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // ======================================================
        // 2. Crear payroll_expenses asociadas a los contratos
        // ======================================================
        DB::table('payroll_expenses')->insert([
            [
                'contract_id' => $contractIds[0],
                'amount'      => 3000,
                'date'        => '2025-01-15',
                'description' => 'Pago de salario mensual - Enero 2025',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'contract_id' => $contractIds[1],
                'amount'      => 2500,
                'date'        => '2025-01-20',
                'description' => 'Pago de salario mensual - Enero 2025',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'contract_id' => $contractIds[0],
                'amount'      => 3000,
                'date'        => '2025-02-15',
                'description' => 'Pago de salario mensual - Febrero 2025',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'contract_id' => $contractIds[1],
                'amount'      => 2500,
                'date'        => '2025-02-20',
                'description' => 'Pago de salario mensual - Febrero 2025',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        echo "✔ Contracts y Payroll Expenses sembrados correctamente.\n";
    }
}
