<?php

namespace App\Catalogo;

use JsonSerializable;

final readonly class Articulo implements JsonSerializable
{
    public function __construct(
        public string $codigo,
        public string $descripcion,
        public string $unidad,          // unidad de venta/almacén (CJA)
        public float $precio,           // precio del nivel, por unidad de venta (caja)
        public float $existencia,       // en unidades de venta (cajas); puede tener fracción
        public float $factor = 1,       // unidades internas por caja (ARTICULO.FACTOR_EMPAQUE)
        public string $unidadEmpaque = '', // unidad interna (VIA, TBL…: ARTICULO.UNIDAD_EMPAQUE)
    ) {}

    public static function fromRow(object|array $row): self
    {
        $row = (object) $row;
        $factor = (float) ($row->factor ?? 1);

        return new self(
            codigo: trim((string) $row->codigo),
            descripcion: trim((string) $row->descripcion),
            unidad: trim((string) ($row->unidad ?? '')),
            precio: round((float) $row->precio, 2),
            existencia: max(0, (float) $row->existencia),
            factor: $factor >= 1 ? $factor : 1,
            unidadEmpaque: trim((string) ($row->unidad_empaque ?? '')),
        );
    }

    /** ¿Se puede capturar en unidades internas (viales, tabletas…)? */
    public function tieneEmpaque(): bool
    {
        return $this->factor > 1;
    }

    /** Máximo en cajas (se permiten fracciones de caja). */
    public function maximoPedible(): float
    {
        return Cantidades::bajar4($this->existencia);
    }

    /** Máximo en unidades internas (viales, tabletas…). */
    public function maximoUnidades(): float
    {
        return Cantidades::unidades($this->existencia, $this->factor);
    }

    public function disponible(): bool
    {
        return $this->maximoUnidades() >= 1;
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
            'factor' => $this->factor,
            'unidad_empaque' => $this->unidadEmpaque,
        ];
    }
}
