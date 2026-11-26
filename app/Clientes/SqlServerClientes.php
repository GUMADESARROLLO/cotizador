<?php

namespace App\Clientes;

use App\Support\SqlLike;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Lectura de clientes en Softland.umk.CLIENTE (alias "c"). Solo SELECT.
 */
class SqlServerClientes implements Clientes
{
    public function __construct(
        private ConnectionInterface $db,
        private string $tabla,
        private array $columnas,
        private ?string $whereRaw = null,
        private int $limite = 30,
        private ?string $vendedor = null, // si se indica, solo clientes de ese vendedor
        /** @var list<string> columnas extra donde buscar */
        private array $buscarEn = [],
    ) {}

    public function buscar(string $termino, ?int $limite = null): array
    {
        $palabras = SqlLike::palabras($termino);
        if ($palabras === []) {
            return [];
        }

        [$codigo, $nombre] = [$this->col('codigo'), $this->col('nombre')];

        // En Softland el RUC va dentro del nombre; si hay columna RUC propia también se busca.
        $donde = [$codigo, $nombre];
        if (isset($this->columnas['ruc'])) {
            $donde[] = $this->col('ruc');
        }
        foreach ($this->buscarEn as $extra) {
            $donde[] = $this->db->getQueryGrammar()->wrap('c.'.$extra);
        }

        $query = $this->base();

        // Cada palabra debe aparecer en alguna de esas columnas.
        foreach ($palabras as $palabra) {
            $like = SqlLike::contiene($palabra);
            $query->where(function (Builder $q) use ($donde, $like) {
                foreach ($donde as $col) {
                    $q->orWhereRaw("{$col} LIKE ? ESCAPE '!'", [$like]);
                }
            });
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

        if ($this->vendedor) {
            $query->where('c.'.$this->columnas['vendedor'], $this->vendedor);
        }

        return $query;
    }

    private function col(string $campo): string
    {
        return $this->db->getQueryGrammar()->wrap('c.'.$this->columnas[$campo]);
    }
}
