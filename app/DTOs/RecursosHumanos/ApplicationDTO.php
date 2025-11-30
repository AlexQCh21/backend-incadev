<?php

namespace App\DTOs\RecursosHumanos;

class ApplicationDTO
{
    public function __construct(
        public int $id,
        public int $offer_id,
        public int $applicant_id,
        public string $applicant_name,
        public string $applicant_email,
        public ?string $applicant_phone,
        public ?string $applicant_dni,
        public string $cv_path,
        public string $status,
        public string $created_at,
        public string $updated_at,
        public ?array $offer = null,
        public ?array $applicant = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            offer_id: $data['offer_id'],
            applicant_id: $data['applicant_id'],
            applicant_name: $data['applicant_name'] ?? '',
            applicant_email: $data['applicant_email'] ?? '',
            applicant_phone: $data['applicant_phone'] ?? null,
            applicant_dni: $data['applicant_dni'] ?? null,
            cv_path: $data['cv_path'],
            status: $data['status'],
            created_at: $data['created_at'],
            updated_at: $data['updated_at'],
            offer: $data['offer'] ?? null,
            applicant: $data['applicant'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'offer_id' => $this->offer_id,
            'applicant_id' => $this->applicant_id,
            'applicant_name' => $this->applicant_name,
            'applicant_email' => $this->applicant_email,
            'applicant_phone' => $this->applicant_phone,
            'applicant_dni' => $this->applicant_dni,
            'cv_path' => $this->cv_path,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'offer' => $this->offer,
            'applicant' => $this->applicant,
        ];
    }
}