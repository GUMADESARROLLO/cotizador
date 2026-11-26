<?php

namespace App\Catalogo;

use JsonSerializable;

final readonly class Articulo implements JsonSerializable
{
    public function __construct(
        public string $codigo,
        public string $descripcion,
        public string $unidad,
        public float $precio,
        public float $existencia,
    ) {}

    public static function fromRow(object|array $row): self
    {
        $row = (object) $row;

        return new self(
            codigo: trim((string) $row->codigo),
            descripcion: trim((string) $row->descripcion),
            unidad: trim((string) ($row->unidad ?? '')),
            precio: round((float) $row->precio, 2),
            existencia: max(0, (float) $row->existencia),
        );
    }

    /** Máximo que se puede pedir: la existencia completa. */
    public function maximoPedible(): float
    {
        return floor($this->existencia);
    }

    public function disponible(): bool
    {
        return $this->maximoPedible() >= 1;
    }

    public function jsonSerialize(): array
    {
        return [
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'unidad' => $this->unidad,
            'precio' => $this->precio,
            'existencia' => $this->existencia,
            'maximo' => $this->maximoPedible(),
        ];
    }
}
