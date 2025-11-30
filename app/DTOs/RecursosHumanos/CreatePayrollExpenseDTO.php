<?php

namespace App\DTOs\RecursosHumanos;

class CreatePayrollExpenseDTO
{
    public function __construct(
        public int $contract_id,
        public float $amount,
        public string $date,
        public ?string $description = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            contract_id: (int) $data['contract_id'],
            amount: (float) $data['amount'],
            date: $data['date'],
            description: $data['description'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'contract_id' => $this->contract_id,
            'amount' => $this->amount,
            'date' => $this->date,
            'description' => $this->description,
        ];
    }
}