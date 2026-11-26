<?php

namespace App\Catalogo;

interface Catalogo
{
    /** @return list<Articulo> */
    public function buscar(string $termino, ?int $limite = null): array;

    public function encontrar(string $codigo): ?Articulo;

    /**
     * @param  list<string>  $codigos
     * @return array<string, Articulo> indexado por código
     */
    public function encontrarVarios(array $codigos): array;
}
