<?php

namespace App\DTOs\Finanzas;

use Carbon\Carbon;

class FiltrosBalanceDTO
{
    public function __construct(
        public ?Carbon $fechaDesde = null,
        public ?Carbon $fechaHasta = null,
        public ?int $cursoId = null,
        public ?int $versionId = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fechaDesde: isset($data['fecha_desde']) ? Carbon::parse($data['fecha_desde']) : null,
            fechaHasta: isset($data['fecha_hasta']) ? Carbon::parse($data['fecha_hasta']) : null,
            cursoId: $data['curso_id'] ?? null,
            versionId: $data['version_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'fecha_desde' => $this->fechaDesde?->toDateString(),
            'fecha_hasta' => $this->fechaHasta?->toDateString(),
            'curso_id' => $this->cursoId,
            'version_id' => $this->versionId
        ]);
    }
}