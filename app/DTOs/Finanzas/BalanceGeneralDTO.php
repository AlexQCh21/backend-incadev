<?php

namespace App\DTOs\Finanzas;

class BalanceGeneralDTO
{
    public function __construct(
        public array $resumen,
        public array $activos,
        public array $pasivos,
        public array $indicadores
    ) {}

    public function toArray(): array
    {
        return [
            'resumen' => $this->resumen,
            'activos' => $this->activos,
            'pasivos' => $this->pasivos, 
            'indicadores' => $this->indicadores
        ];
    }

    // Método para acceder a propiedades como objeto
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }

    // Método para verificar si existe una propiedad
    public function __isset($name)
    {
        return property_exists($this, $name);
    }
}