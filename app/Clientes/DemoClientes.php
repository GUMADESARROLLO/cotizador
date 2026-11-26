<?php

namespace App\Clientes;

use App\Support\SqlLike;
use Illuminate\Support\Str;

/**
 * Clientes en memoria para probar la interfaz y para los tests.
 */
class DemoClientes implements Clientes
{
    /** @var array<string, Cliente> */
    private array $clientes = [];

    public function __construct(?array $filas = null)
    {
        foreach ($filas ?? self::filasDeEjemplo() as $fila) {
            $c = Cliente::fromRow($fila);
            $this->clientes[$c->codigo] = $c;
        }
    }

    public function buscar(string $termino, ?int $limite = null): array
    {
        $palabras = array_map(Str::lower(...), SqlLike::palabras($termino));
        if ($palabras === []) {
            return [];
        }

        $res = array_filter($this->clientes, function (Cliente $c) use ($palabras) {
            $texto = Str::lower("{$c->codigo} {$c->nombre} {$c->ruc}");
            foreach ($palabras as $p) {
                if (! str_contains($texto, $p)) {
                    return false;
                }
            }

            return true;
        });

        return array_slice(array_values($res), 0, $limite ?? 30);
    }

    public function encontrar(string $codigo): ?Cliente
    {
        return $this->clientes[trim($codigo)] ?? null;
    }

    private static function filasDeEjemplo(): array
    {
        $f = fn ($codigo, $nombre, $moroso, $disponible) => [
            'codigo' => $codigo, 'nombre' => $nombre, 'direccion' => 'Managua', 'ruc' => 'J03100000'.$codigo,
            'vendedor' => 'F01', 'moroso' => $moroso, 'credito' => 50000, 'saldo' => 50000 - $disponible, 'disponible' => $disponible,
        ];

        return [
            $f('00003', 'FARMACIA LA PROVIDENCIA', 'N', 2500),
            $f('00004', 'FARMACIA LA UNION', 'S', 2500),
            $f('00005', 'FARMACIA LOPEZ', 'N', 11300),
            $f('00010', 'HOSPITAL CENTRAL', 'N', 0),
        ];
    }
}
