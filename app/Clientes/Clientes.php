<?php

namespace App\Clientes;

interface Clientes
{
    /** @return list<Cliente> */
    public function buscar(string $termino, ?int $limite = null): array;

    public function encontrar(string $codigo): ?Cliente;
}
