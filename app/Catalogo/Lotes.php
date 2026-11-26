<?php

namespace App\Catalogo;

/**
 * Lotes con existencia en la bodega del pedido. El primero de cada lista es
 * el que vence primero (el sugerido); quien arma el pedido puede elegir otro.
 */
interface Lotes
{
    /**
     * @param  list<string>  $articulos
     * @return array<string, list<Lote>> código => lotes ordenados por vencimiento
     */
    public function porArticulos(array $articulos): array;
}
