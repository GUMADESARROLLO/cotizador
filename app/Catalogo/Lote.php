<?php

namespace App\Catalogo;

final readonly class Lote
{
    public function __construct(
        public string $lote,
        public float $disponible,
        public ?string $vence, // Y-m-d
    ) {}

    /** Máximo en cajas (se permiten fracciones de caja). */
    public function maximoPedible(): float
    {
        return Cantidades::bajar4($this->disponible);
    }

    /** Máximo en unidades internas para un factor de empaque. */
    public function maximoUnidades(float $factor): float
    {
        return Cantidades::unidades($this->disponible, $factor);
    }

    public function toArray(): array
    {
        return [
            'lote' => $this->lote,
            'disponible' => $this->disponible,
            'maximo' => $this->maximoPedible(),
            'vence' => $this->vence,
        ];
    }
}
