<?php

namespace App\DTOs\RecursosHumanos;

class UpdateEmployeeDTO
{
    public function __construct(
        public string $fullname,
        public string $email,
        public string $dni,
        public ?string $phone,
        public string $staff_type,
        public string $payment_type,
        public float $amount,
        public string $start_date
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            fullname: $data['fullname'],
            email: $data['email'],
            dni: $data['dni'],
            phone: $data['phone'] ?? null,
            staff_type: $data['staff_type'],
            payment_type: $data['payment_type'],
            amount: (float) $data['amount'],
            start_date: $data['start_date']
        );
    }

    public function toArray(): array
    {
        return [
            'fullname' => $this->fullname,
            'email' => $this->email,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'staff_type' => $this->staff_type,
            'payment_type' => $this->payment_type,
            'amount' => $this->amount,
            'start_date' => $this->start_date,
        ];
    }
}