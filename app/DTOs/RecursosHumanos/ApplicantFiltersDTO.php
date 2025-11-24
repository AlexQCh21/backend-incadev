<?php

namespace App\DTOs\RecursosHumanos;

class ApplicantFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $offer_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            offer_id: $data['offer_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'offer_id' => $this->offer_id,
        ];
    }

    public function hasSearch(): bool
    {
        return !empty($this->search);
    }

    public function hasStatus(): bool
    {
        return !empty($this->status) && $this->status !== 'all';
    }

    public function hasOffer(): bool
    {
        return !empty($this->offer_id) && $this->offer_id !== 'all';
    }
}