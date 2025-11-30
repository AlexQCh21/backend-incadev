<?php

namespace App\DTOs\RecursosHumanos;

class CreateContractDTO
{
    public function __construct(
        public string $staff_type,
        public string $payment_type,
        public float $amount,
        public string $start_date,
        public ?string $end_date = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            staff_type: $data['staff_type'],
            payment_type: $data['payment_type'],
            amount: (float) $data['amount'],
            start_date: $data['start_date'],
            end_date: $data['end_date'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'staff_type' => $this->staff_type,
            'payment_type' => $this->payment_type,
            'amount' => $this->amount,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ];
    }
}