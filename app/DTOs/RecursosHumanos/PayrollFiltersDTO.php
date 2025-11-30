<?php

namespace App\DTOs\RecursosHumanos;

class PayrollFiltersDTO
{
    public function __construct(
        public ?int $employee_id = null,
        public ?int $contract_id = null,
        public ?string $sort_by = null, // ✅ NUEVO: filtro de ordenamiento
        public ?string $start_date = null,
        public ?string $end_date = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            employee_id: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            contract_id: isset($data['contract_id']) ? (int) $data['contract_id'] : null,
            sort_by: $data['sort_by'] ?? null, // ✅ NUEVO
            start_date: $data['start_date'] ?? null,
            end_date: $data['end_date'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'employee_id' => $this->employee_id,
            'contract_id' => $this->contract_id,
            'sort_by' => $this->sort_by, // ✅ NUEVO
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ]);
    }
}