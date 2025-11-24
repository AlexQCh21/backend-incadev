<?php

namespace App\DTOs\RecursosHumanos;

use IncadevUns\CoreDomain\Enums\OfferStatus;

class OfferDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?array $requirements,
        public ?string $closing_date,
        public int $available_positions,
        public int $applications_count,
        public int $pending_applications,
        public int $accepted_applications,
        public OfferStatus $status, // Cambiar a enum
        public string $created_at,
        public string $updated_at
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'] ?? null,
            requirements: $data['requirements'] ?? [],
            closing_date: $data['closing_date'] ?? null,
            available_positions: $data['available_positions'] ?? 1,
            applications_count: $data['applications_count'] ?? 0,
            pending_applications: $data['pending_applications'] ?? 0,
            accepted_applications: $data['accepted_applications'] ?? 0,
            status: OfferStatus::from($data['status'] ?? 'active'), // Convertir a enum
            created_at: $data['created_at'],
            updated_at: $data['updated_at']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'closing_date' => $this->closing_date,
            'available_positions' => $this->available_positions,
            'applications_count' => $this->applications_count,
            'pending_applications' => $this->pending_applications,
            'accepted_applications' => $this->accepted_applications,
            'status' => $this->status->value, // Usar value del enum
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}