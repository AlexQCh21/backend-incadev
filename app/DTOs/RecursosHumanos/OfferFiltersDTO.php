<?php

namespace App\DTOs\RecursosHumanos;

class OfferFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public string $status = 'all'
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? 'all'
        );
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
        ];
    }
}