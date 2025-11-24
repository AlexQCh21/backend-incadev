<?php

namespace App\DTOs\RecursosHumanos;

class UpdateContractDTO
{
    public function __construct(
        public ?string $end_date = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            end_date: $data['end_date'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'end_date' => $this->end_date,
        ]);
    }
}