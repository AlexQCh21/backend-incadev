<?php

namespace App\DTOs\RecursosHumanos;

class UpdateOfferDTO
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?array $requirements,
        public ?string $closing_date,
        public int $available_positions,
        public ?string $status
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            requirements: $data['requirements'] ?? [],
            closing_date: $data['closing_date'] ?? null,
            available_positions: $data['available_positions'] ?? 1,
            status: $data['status'] ?? null
        );
    }
}