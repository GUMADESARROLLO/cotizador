<?php

namespace App\Catalogo;

/**
 * Lotes en memoria para la interfaz demo y los tests.
 * Por defecto: un lote por artículo con toda su existencia, vence en un año.
 */
class DemoLotes implements Lotes
{
    /** @param  array<string, list<Lote>>|null  $lotes  si se indica, se usan estos */
    public function __construct(
        private ?Catalogo $catalogo = null,
        private ?array $lotes = null,
    ) {}

    public function porArticulos(array $articulos): array
    {
        $articulos = array_map('strval', $articulos);

        if ($this->lotes !== null) {
            return array_intersect_key($this->lotes, array_flip($articulos));
        }

        $res = [];
        foreach ($this->catalogo?->encontrarVarios($articulos) ?? [] as $a) {
            if ($a->disponible()) {
                $res[$a->codigo] = [new Lote('L-'.$a->codigo, $a->existencia, now()->addYear()->format('Y-m-d'))];
            }
        }

        return $res;
    }
}
