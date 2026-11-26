<?php

namespace App\Catalogo;

use Illuminate\Database\ConnectionInterface;

/**
 * Lotes desde Softland.umk.EXISTENCIA_LOTE + umk.LOTE. Solo SELECT.
 * Solo lotes aprobados (LOTE.ESTADO = 'A') con existencia (puede ser fracción de caja).
 */
class SqlServerLotes implements Lotes
{
    public function __construct(
        private ConnectionInterface $db,
        private string $esquema,
        private string $bodega,
        private string $estado = 'A',
    ) {}

    public function porArticulos(array $articulos): array
    {
        // Siempre texto (claves numéricas de PHP llegan como int; ver SqlServerCatalogo).
        $articulos = array_values(array_unique(array_filter(array_map('strval', $articulos), 'strlen')));
        if ($articulos === []) {
            return [];
        }

        $resultado = [];
        foreach (array_chunk($articulos, 1000) as $bloque) {
            $this->db->table($this->esquema.'.EXISTENCIA_LOTE as el')
                ->join($this->esquema.'.LOTE as l', function ($j) {
                    $j->on('l.ARTICULO', '=', 'el.ARTICULO')->on('l.LOTE', '=', 'el.LOTE');
                })
                ->select(['el.ARTICULO as articulo', 'el.LOTE as lote', 'el.CANT_DISPONIBLE as disponible', 'l.FECHA_VENCIMIENTO as vence'])
                ->where('el.BODEGA', $this->bodega)
                ->where('el.CANT_DISPONIBLE', '>', 0)
                ->where('l.ESTADO', $this->estado)
                ->whereIn('el.ARTICULO', $bloque)
                ->orderBy('el.ARTICULO')
                ->orderBy('l.FECHA_VENCIMIENTO')
                ->orderBy('el.LOTE')
                ->get()
                ->each(function ($r) use (&$resultado) {
                    $resultado[trim($r->articulo)][] = new Lote(
                        lote: trim($r->lote),
                        disponible: (float) $r->disponible,
                        vence: $r->vence ? substr((string) $r->vence, 0, 10) : null,
                    );
                });
        }

        return $resultado;
    }
}
