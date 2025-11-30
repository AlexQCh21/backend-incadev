<?php

namespace App\DTOs\RecursosHumanos;

use Carbon\Carbon;

class EmployeeDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $fullname,
        public ?string $dni,
        public ?string $phone,
        public array $roles,
        public array $contracts,
        public bool $is_active,
        public ?array $active_contract,
        public ?array $last_contract, // ✅ AGREGAR este campo
        public string $created_at
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            fullname: $data['fullname'] ?? '',
            dni: $data['dni'] ?? null,
            phone: $data['phone'] ?? null,
            roles: $data['roles'] ?? [],
            contracts: $data['contracts'] ?? [],
            is_active: $data['is_active'] ?? false,
            active_contract: $data['active_contract'] ?? null,
            last_contract: $data['last_contract'] ?? null, // ✅ AGREGAR
            created_at: $data['created_at'] ?? now()->toISOString()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user' => [ // ✅ ESTRUCTURA QUE ESPERA EL FRONTEND
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'fullname' => $this->fullname,
                'dni' => $this->dni,
                'phone' => $this->phone,
            ],
            'contracts' => $this->contracts,
            'roles' => $this->roles,
            'is_active' => $this->is_active,
            'active_contract' => $this->active_contract,
            'last_contract' => $this->last_contract, // ✅ AGREGAR
            'created_at' => $this->created_at,
        ];
    }
}