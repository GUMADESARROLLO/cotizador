<?php

namespace App\Clientes;

use App\Support\Orden;
use App\Support\SqlLike;
use Illuminate\Support\Str;

/**
 * Clientes en memoria para probar la interfaz y para los tests.
 */
class DemoClientes implements Clientes
{
    /** @var array<string, Cliente> */
    private array $clientes = [];

    public function __construct(?array $filas = null, ?string $vendedor = null)
    {
        foreach ($filas ?? self::filasDeEjemplo() as $fila) {
            $c = Cliente::fromRow($fila);
            if ($vendedor && $c->vendedor !== $vendedor) {
                continue; // solo clientes del vendedor configurado
            }
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

    public function listar(string $q, string $filtro, int $pagina, int $porPagina, ?Orden $orden = null): array
    {
        $lista = array_values(array_filter($this->coincidencias($q), fn (Cliente $c) => $this->cumple($c, $filtro)));

        if ($orden) {
            $valor = fn (Cliente $c) => match ($orden->campo) {
                'cliente' => $c->nombre,
                'nivel' => $c->nivelPrecio,
                'limite' => $c->credito,
                'saldo' => $c->saldo,
                'disponible' => $c->disponible,
                'telefonos' => trim($c->telefono1) !== '' ? $c->telefono1 : null,
                default => null,
            };
            usort($lista, function (Cliente $a, Cliente $b) use ($valor, $orden) {
                [$x, $y] = [$valor($a), $valor($b)];
                if (($x === null) !== ($y === null)) {
                    return $x === null ? 1 : -1; // vacíos al final
                }
                $r = is_string($x) ? strnatcasecmp($x, (string) $y) : $x <=> $y;

                return ($orden->desc ? -$r : $r) ?: strcmp($a->nombre, $b->nombre);
            });
        }

        return [
            'items' => array_slice($lista, (max(1, $pagina) - 1) * $porPagina, $porPagina),
            'total' => count($lista),
        ];
    }

    public function contar(string $q): array
    {
        $lista = $this->coincidencias($q);

        return [
            'todos' => count($lista),
            'morosos' => count(array_filter($lista, fn (Cliente $c) => $c->moroso)),
            'saldo' => count(array_filter($lista, fn (Cliente $c) => $c->saldo != 0)),
        ];
    }

    /** @return list<Cliente> ordenados por nombre; q vacío = todos */
    private function coincidencias(string $q): array
    {
        $palabras = array_map(Str::lower(...), SqlLike::palabras($q));
        $res = array_values(array_filter($this->clientes, function (Cliente $c) use ($palabras) {
            $texto = Str::lower("{$c->codigo} {$c->nombre} {$c->ruc}");
            foreach ($palabras as $p) {
                if (! str_contains($texto, $p)) {
                    return false;
                }
            }

            return true;
        }));
        usort($res, fn (Cliente $a, Cliente $b) => strcmp($a->nombre, $b->nombre));

        return $res;
    }

    private function cumple(Cliente $c, string $filtro): bool
    {
        return match ($filtro) {
            'morosos' => $c->moroso,
            'saldo' => $c->saldo != 0,
            default => true,
        };
    }

    public function encontrar(string $codigo): ?Cliente
    {
        return $this->clientes[trim($codigo)] ?? null;
    }

    private static function filasDeEjemplo(): array
    {
        $f = fn ($codigo, $nombre, $moroso, $disponible, $vendedor = 'F12', $nivel = 'INST.PUB. C$', $monedaNivel = 'L', $moneda = 'COR') => [
            'codigo' => $codigo, 'nombre' => $nombre, 'direccion' => 'Managua', 'ruc' => 'J03100000'.$codigo,
            'vendedor' => $vendedor, 'moroso' => $moroso, 'credito' => 50000, 'saldo' => 50000 - $disponible, 'disponible' => $disponible,
            'telefono1' => '2270-'.$codigo, 'telefono2' => '', 'condicion_pago' => '180', 'nivel_precio' => $nivel, 'moneda_nivel' => $monedaNivel, 'moneda' => $moneda,
        ];

        return [
            $f('00003', 'FARMACIA LA PROVIDENCIA', 'N', 2500),
            $f('00004', 'FARMACIA LA UNION', 'S', 2500),
            $f('00005', 'FARMACIA LOPEZ', 'N', 11300),
            $f('00010', 'HOSPITAL CENTRAL', 'N', 0),
            $f('00020', 'FARMACIA DE OTRO VENDEDOR', 'N', 5000, 'F01'),
            $f('00030', 'ORGANISMO EN DOLARES', 'N', 9000, 'F12', 'INST.PRIV.U$', 'D', 'DOL'),
            $f('00031', 'CLINICA PRECIO C$ CREDITO US$', 'N', 9000, 'F12', 'FARMACIA', 'L', 'DOL'),
        ];
    }
}
