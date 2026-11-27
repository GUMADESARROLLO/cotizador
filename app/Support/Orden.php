<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Orden elegido al tocar el encabezado de una columna (como en DataTables).
 *
 * Viene en la URL como ?o=campo&d=asc|desc. Solo se aceptan los campos que
 * cada pantalla permite: nunca llega a SQL un nombre de columna del usuario,
 * cada listado traduce el campo a su propia expresión.
 */
final readonly class Orden
{
    public function __construct(
        public string $campo,
        public bool $desc = false,
    ) {}

    /** @param  list<string>  $permitidos */
    public static function desde(Request $request, array $permitidos): ?self
    {
        $campo = (string) $request->query('o', '');

        return in_array($campo, $permitidos, true)
            ? new self($campo, $request->query('d') === 'desc')
            : null;
    }

    public function dir(): string
    {
        return $this->desc ? 'desc' : 'asc';
    }

    /** Valor de aria-sort para el encabezado de $campo. */
    public static function aria(?self $orden, string $campo): string
    {
        if ($orden?->campo !== $campo) {
            return 'none';
        }

        return $orden->desc ? 'descending' : 'ascending';
    }
}
