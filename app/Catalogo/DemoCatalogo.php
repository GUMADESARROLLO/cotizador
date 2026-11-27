<?php

namespace App\Catalogo;

use App\Support\Orden;
use Illuminate\Support\Str;

/**
 * Catálogo en memoria para probar la interfaz y para los tests.
 */
class DemoCatalogo implements Catalogo
{
    /** @var array<string, Articulo> */
    private array $articulos = [];

    /** @param  list<array>|null  $filas */
    public function __construct(?array $filas = null)
    {
        foreach ($filas ?? self::filasDeEjemplo() as $fila) {
            $a = Articulo::fromRow($fila);
            $this->articulos[$a->codigo] = $a;
        }
    }

    public function buscar(string $termino, ?int $limite = null): array
    {
        $palabras = preg_split('/\s+/', Str::lower(trim($termino)), -1, PREG_SPLIT_NO_EMPTY);
        if ($palabras === []) {
            return [];
        }

        $res = array_filter($this->articulos, function (Articulo $a) use ($palabras) {
            $texto = Str::lower($a->codigo.' '.$a->descripcion);
            foreach ($palabras as $p) {
                if (! str_contains($texto, $p)) {
                    return false;
                }
            }

            return true;
        });

        return array_slice(array_values($res), 0, $limite ?? 30);
    }

    public function listar(string $q, string $filtro, int $pagina, int $porPagina, array $codigosPrioridad = [], ?Orden $orden = null, array $valoresOrden = []): array
    {
        $todos = trim($q) === '' ? array_values($this->articulos) : $this->buscar($q, PHP_INT_MAX);

        $prioridad = array_map('strval', $codigosPrioridad);
        $todos = match ($filtro) {
            'existencia' => array_values(array_filter($todos, fn (Articulo $a) => $a->existencia > 0)),
            'prioridad' => array_values(array_filter($todos, fn (Articulo $a) => in_array($a->codigo, $prioridad, true))),
            default => $todos,
        };

        if ($orden) {
            $valor = fn (Articulo $a) => match ($orden->campo) {
                'articulo' => $a->descripcion,
                'codigo' => $a->codigo,
                'existencia' => $a->existencia,
                'precio' => $a->precio,
                default => $valoresOrden[$a->codigo] ?? null,
            };
            // Sin valor al final; empate: descripción (como en SQL Server).
            usort($todos, function (Articulo $a, Articulo $b) use ($valor, $orden) {
                [$x, $y] = [$valor($a), $valor($b)];
                if (($x === null) !== ($y === null)) {
                    return $x === null ? 1 : -1;
                }
                $r = is_string($x) ? strnatcasecmp($x, (string) $y) : $x <=> $y;

                return ($orden->desc ? -$r : $r) ?: strcmp($a->descripcion, $b->descripcion);
            });
        }

        return [
            'items' => array_slice($todos, (max(1, $pagina) - 1) * $porPagina, $porPagina),
            'total' => count($todos),
        ];
    }

    public function encontrar(string $codigo): ?Articulo
    {
        return $this->articulos[$codigo] ?? null;
    }

    public function encontrarVarios(array $codigos): array
    {
        return array_intersect_key($this->articulos, array_flip($codigos));
    }

    private static function filasDeEjemplo(): array
    {
        $f = fn ($codigo, $descripcion, $unidad, $precio, $existencia) => compact('codigo', 'descripcion', 'unidad', 'precio', 'existencia');

        return [
            $f('TOR-0001', 'Tornillo hexagonal 1/4" x 1" galvanizado', 'PZA', 1.85, 1250),
            $f('TOR-0002', 'Tornillo hexagonal 3/8" x 2" galvanizado', 'PZA', 3.40, 640),
            $f('TUE-0010', 'Tuerca hexagonal 1/4" galvanizada', 'PZA', 0.65, 3000),
            $f('RON-0020', 'Rondana plana 1/4" galvanizada', 'PZA', 0.35, 0),
            $f('CAB-1210', 'Cable THW calibre 12 rojo', 'MTS', 9.90, 480),
            $f('CAB-1410', 'Cable THW calibre 14 negro', 'MTS', 7.20, 35),
            $f('PIN-5001', 'Pintura vinílica blanca 19 L', 'CUB', 1450.00, 12),
            $f('PIN-5002', 'Pintura esmalte negro 4 L', 'GAL', 520.00, 3),
            $f('TUB-0750', 'Tubo PVC hidráulico 3/4" x 6 m', 'TRA', 128.50, 90),
            $f('COD-0750', 'Codo PVC 90° 3/4"', 'PZA', 6.80, 410),
            $f('CIN-0001', 'Cinta de aislar negra 18 mm', 'PZA', 18.00, 220),
            $f('BRO-0001', 'Broca para concreto 1/4"', 'PZA', 42.00, 1),
            // Con empaque: 50 viales por caja (como 13705183 en Softland).
            ['codigo' => 'VIA-0050', 'descripcion' => 'Ampicilina 1 g vial', 'unidad' => 'CJA', 'precio' => 1646.50, 'existencia' => 60.26, 'factor' => 50, 'unidad_empaque' => 'VIA'],
        ];
    }
}
