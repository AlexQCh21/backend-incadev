<?php

namespace App\DTOs\Finanzas;

use App\DTOs\Finanzas\FiltrosBalanceDTO;

interface BalanceGeneralRepositoryInterface
{
    public function obtenerResumen(FiltrosBalanceDTO $filtros): array;
    public function obtenerActivos(FiltrosBalanceDTO $filtros): array;
    public function obtenerPasivos(FiltrosBalanceDTO $filtros): array;
    public function calcularIndicadores(FiltrosBalanceDTO $filtros): array;
}