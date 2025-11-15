<?php

namespace App\DTOs\Finanzas;

use Illuminate\Http\Resources\Json\JsonResource;

class BalanceGeneralResource extends JsonResource
{
    public function toArray($request)
    {
        // Acceder a las propiedades del objeto DTO
        return [
            'resumen' => [
                'ingresos_verificados' => $this->resumen['ingresos_verificados'] ?? 0,
                'pagos_pendientes' => $this->resumen['pagos_pendientes'] ?? 0,
                'ingresos_mes_actual' => $this->resumen['ingresos_mes_actual'] ?? 0,
                'gastos_nomina' => $this->resumen['gastos_nomina'] ?? 0,
                'patrimonio_neto' => $this->resumen['patrimonio_neto'] ?? 0,
                'cursos_activos' => $this->resumen['cursos_activos'] ?? 0,
                'matriculas_pagadas' => $this->resumen['matriculas_pagadas'] ?? 0,
            ],
            'activos' => $this->activos ?? [],
            'pasivos' => $this->pasivos ?? [],
            'indicadores' => [
                'eficiencia_cobranza' => round($this->indicadores['eficiencia_cobranza'] ?? 0, 2),
                'margen_neto' => round($this->indicadores['margen_neto'] ?? 0, 2),
                'ingreso_promedio_por_curso' => round($this->indicadores['ingreso_promedio_por_curso'] ?? 0, 2),
                'tasa_retencion' => round($this->indicadores['tasa_retencion'] ?? 0, 2),
            ]
        ];
    }
}