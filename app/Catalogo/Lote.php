<?php

namespace App\Catalogo;

final readonly class Lote
{
    public function __construct(
        public string $lote,
        public float $disponible,
        public ?string $vence, // Y-m-d
    ) {}

    public function maximoPedible(): float
    {
        return floor($this->disponible);
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
