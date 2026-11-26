<?php

namespace App\Clientes;

use App\Support\SqlLike;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Lectura de clientes en SQL Server (alias "c"). Solo SELECT.
 */
class SqlServerClientes implements Clientes
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tabla,
        private array $columnas,
        private ?string $whereRaw = null,
        private int $limite = 30,
    ) {}

    public function buscar(string $termino, ?int $limite = null): array
    {
        $palabras = SqlLike::palabras($termino);
        if ($palabras === []) {
            return [];
        }

        [$codigo, $nombre, $ruc] = [$this->col('codigo'), $this->col('nombre'), $this->col('ruc')];
        $query = $this->base();

        // Cada palabra debe aparecer en el código, el nombre o el RUC.
        foreach ($palabras as $palabra) {
            $like = SqlLike::contiene($palabra);
            $query->where(fn (Builder $q) => $q
                ->whereRaw("{$codigo} LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("{$nombre} LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("{$ruc} LIKE ? ESCAPE '!'", [$like]));
        }

        return $query
            ->orderByRaw("CASE WHEN {$codigo} = ? THEN 0 ELSE 1 END", [trim($termino)])
            ->orderByRaw($nombre)
            ->limit($limite ?? $this->limite)
            ->get()
            ->map(Cliente::fromRow(...))
            ->all();
    }

    public function encontrar(string $codigo): ?Cliente
    {
        $row = $this->base()->where('c.'.$this->columnas['codigo'], trim($codigo))->first();

        return $row ? Cliente::fromRow($row) : null;
    }

    private function base(): Builder
    {
        $select = [];
        foreach ($this->columnas as $campo => $columna) {
            $select[] = "c.{$columna} as {$campo}";
        }

        $query = $this->db->table($this->tabla.' as c')->select($select);

        if ($this->whereRaw) {
            $query->whereRaw($this->whereRaw);
        }

        return $query;
    }

    private function col(string $campo): string
    {
        return $this->db->getQueryGrammar()->wrap('c.'.$this->columnas[$campo]);
    }
}
