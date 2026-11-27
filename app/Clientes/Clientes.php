<?php

namespace App\Clientes;

use App\Support\Orden;

interface Clientes
{
    /** @return list<Cliente> */
    public function buscar(string $termino, ?int $limite = null): array;

    /**
     * Página de la cartera del vendedor. `q` vacío = toda la cartera.
     * Filtros: todos | morosos | saldo (saldo distinto de cero).
     * Orden (encabezado): cliente | nivel | limite | saldo | disponible | telefonos.
     *
     * @return array{items: list<Cliente>, total: int} total = coincidencias con q y filtro
     */
    public function listar(string $q, string $filtro, int $pagina, int $porPagina, ?Orden $orden = null): array;

    /**
     * Contadores del segmentado para la búsqueda `q` (sin aplicar filtro).
     *
     * @return array{todos: int, morosos: int, saldo: int}
     */
    public function contar(string $q): array;

    public function encontrar(string $codigo): ?Cliente;
}
