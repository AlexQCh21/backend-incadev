<?php

namespace App\DTOs\RecursosHumanos;

class CreateApplicationDTO
{
    public function __construct(
        public int $offer_id,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $dni,
        public string $cv_path
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            offer_id: $data['offer_id'],
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            dni: $data['dni'] ?? null,
            cv_path: $data['cv_path']
        );
    }
}