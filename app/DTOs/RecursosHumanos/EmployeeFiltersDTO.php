<?php

namespace App\DTOs\RecursosHumanos;

class EmployeeFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public string $status = 'all' // Asegúrate de que el valor por defecto sea 'all'
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? 'all' // Valor por defecto 'all'
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
        ]);
    }
}