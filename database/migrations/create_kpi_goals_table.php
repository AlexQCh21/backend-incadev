<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kpi_goals', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Nombre del KPI
            $table->string('display_name'); // Nombre para mostrar
            $table->decimal('goal_value', 5, 2)->default(0); // Meta (ej: 85.00%)
            $table->decimal('current_value', 5, 2)->default(0); // Valor actual calculado
            $table->decimal('previous_value', 5, 2)->default(0); // Valor del mes anterior para calcular tendencia
            $table->decimal('trend', 5, 2)->default(0); // Tendencia (+2.4%, -1.8%)
            $table->enum('status', ['Requiere atención', 'En camino', 'Cumplido'])->default('Requiere atención');
            $table->timestamps();
        });

        // Insertar los 4 KPIs iniciales con metas predeterminadas
        DB::table('kpi_goals')->insert([
            [
                'name' => 'satisfaccion_estudiantil',
                'display_name' => 'Satisfacción Estudiantil',
                'goal_value' => 85.00,
                'current_value' => 0,
                'previous_value' => 0,
                'trend' => 0,
                'status' => 'Requiere atención',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ejecucion_presupuestal',
                'display_name' => 'Ejecución Presupuestal',
                'goal_value' => 90.00,
                'current_value' => 0,
                'previous_value' => 0,
                'trend' => 0,
                'status' => 'Requiere atención',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'satisfaccion_instructores',
                'display_name' => 'Satisfacción con Instructores',
                'goal_value' => 88.00,
                'current_value' => 0,
                'previous_value' => 0,
                'trend' => 0,
                'status' => 'Requiere atención',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'empleabilidad_graduados',
                'display_name' => 'Tasa de Empleabilidad de Graduados',
                'goal_value' => 75.00,
                'current_value' => 0,
                'previous_value' => 0,
                'trend' => 0,
                'status' => 'Requiere atención',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_goals');
    }
};
