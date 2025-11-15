<?php

namespace App\Services\Finanzas;

use App\DTOs\Finanzas\BalanceGeneralRepositoryInterface;
use App\DTOs\Finanzas\BalanceGeneralDTO;
use App\DTOs\Finanzas\FiltrosBalanceDTO;
use Carbon\Carbon;

class BalanceGeneralService
{
    public function __construct(
        private BalanceGeneralRepositoryInterface $repository
    ) {}

    /**
     * Obtener balance general con filtros aplicados
     */
    public function obtenerBalanceGeneral(array $filtros = []): BalanceGeneralDTO
    {
        $filtrosDTO = FiltrosBalanceDTO::fromArray($filtros);
        
        return new BalanceGeneralDTO(
            resumen: $this->repository->obtenerResumen($filtrosDTO),
            activos: $this->repository->obtenerActivos($filtrosDTO),
            pasivos: $this->repository->obtenerPasivos($filtrosDTO),
            indicadores: $this->repository->calcularIndicadores($filtrosDTO)
        );
    }

    /**
     * Obtener balance general del mes actual
     */
    public function obtenerBalanceMesActual(): BalanceGeneralDTO
    {
        $filtros = new FiltrosBalanceDTO(
            fechaDesde: Carbon::now()->startOfMonth(),
            fechaHasta: Carbon::now()->endOfMonth()
        );

        return $this->obtenerBalanceGeneral($filtros->toArray());
    }
}