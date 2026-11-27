<?php

namespace App\Catalogo;

use App\Support\Orden;

interface Catalogo
{
    /** @return list<Articulo> */
    public function buscar(string $termino, ?int $limite = null): array;

    public function encontrar(string $codigo): ?Articulo;

    /**
     * Catálogo paginado (pagina desde 1). $q vacío = todo el catálogo.
     * Filtros: "todos", "existencia" (existencia > 0) y "prioridad" (solo los $codigosPrioridad).
     *
     * Orden ($orden, al tocar el encabezado): articulo | codigo | existencia | precio, o
     * categoria | meses, que no están en el catálogo: sus valores llegan en $valoresOrden
     * (código => valor; los que no aparecen van al final). Sin orden: el de siempre.
     *
     * @param  list<string>  $codigosPrioridad  códigos que cuentan como prioridad (filtro "prioridad")
     * @param  array<string, string|float|null>  $valoresOrden
     * @return array{items: list<Articulo>, total: int}
     */
    public function listar(string $q, string $filtro, int $pagina, int $porPagina, array $codigosPrioridad = [], ?Orden $orden = null, array $valoresOrden = []): array;

    /**
     * @param  list<string>  $codigos
     * @return array<string, Articulo> indexado por código
     */
    public function encontrarVarios(array $codigos): array;
}
