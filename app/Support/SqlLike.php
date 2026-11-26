<?php

namespace App\Support;

/**
 * Escapa comodines para usar con LIKE ... ESCAPE '!' (SQL Server y SQLite).
 */
final class SqlLike
{
    public static function contiene(string $valor): string
    {
        return '%'.strtr($valor, ['!' => '!!', '%' => '!%', '_' => '!_', '[' => '![']).'%';
    }

    /** @return list<string> */
    public static function palabras(string $termino): array
    {
        return preg_split('/\s+/', trim($termino), -1, PREG_SPLIT_NO_EMPTY);
    }
}
