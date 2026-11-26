<?php

namespace App\Catalogo;

use App\Support\SqlLike;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Lectura del catálogo en SQL Server. Solo SELECT.
 *
 * La tabla principal se consulta con alias "a". Si se configura una tabla de
 * existencias, se une como subconsulta agrupada (alias "s") con la suma por
 * artículo, excluyendo las bodegas indicadas.
 */
class SqlServerCatalogo implements Catalogo
{
    /**
     * @param  array{codigo:string,descripcion:string,unidad:string,precio:string,existencia:string}  $columnas
     * @param  array{table:?string,articulo:string,cantidad:string,bodega:string,excluir:list<string>}|null  $stock
     */
    public function __construct(
        private ConnectionInterface $db,
        private string $tabla,
        private array $columnas,
        private ?string $whereRaw = null,
        private int $limite = 30,
        private ?array $stock = null,
        /** @var array{table:?string,articulo:string,precio:string,nivel_columna:string,nivel:string,where_raw:?string}|null */
        private ?array $precio = null,
    ) {}

    public function buscar(string $termino, ?int $limite = null): array
    {
        $termino = trim($termino);
        if ($termino === '') {
            return [];
        }

        $codigo = $this->col('codigo');
        $descripcion = $this->col('descripcion');
        $query = $this->base();

        // Cada palabra debe aparecer en el código o en la descripción.
        foreach (SqlLike::palabras($termino) as $palabra) {
            $like = SqlLike::contiene($palabra);
            $query->where(function (Builder $q) use ($codigo, $descripcion, $like) {
                $q->whereRaw("{$codigo} LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("{$descripcion} LIKE ? ESCAPE '!'", [$like]);
            });
        }

        // Coincidencia exacta de código primero, luego por descripción.
        $query->orderByRaw("CASE WHEN {$codigo} = ? THEN 0 ELSE 1 END", [$termino])
            ->orderByRaw($descripcion);

        return $query->limit($limite ?? $this->limite)
            ->get()
            ->map(Articulo::fromRow(...))
            ->all();
    }

    public function encontrar(string $codigo): ?Articulo
    {
        return $this->encontrarVarios([$codigo])[$codigo] ?? null;
    }

    public function encontrarVarios(array $codigos): array
    {
        // Siempre como texto: PHP convierte las claves numéricas ("13401062") en int, y
        // comparar la columna varchar con un int hace que SQL Server convierta TODA la
        // columna a número y falle con códigos como "10107011-B".
        $codigos = array_values(array_unique(array_filter(array_map('strval', $codigos), 'strlen')));
        if ($codigos === []) {
            return [];
        }

        return $this->base()
            ->whereIn('a.'.$this->columnas['codigo'], $codigos)
            ->get()
            ->map(Articulo::fromRow(...))
            ->keyBy('codigo')
            ->all();
    }

    private function base(): Builder
    {
        $c = $this->columnas;

        $query = $this->db->table($this->tabla.' as a')->select([
            "a.{$c['codigo']} as codigo",
            "a.{$c['descripcion']} as descripcion",
            "a.{$c['unidad']} as unidad",
        ]);

        if ($this->precio['table'] ?? null) {
            // Precio del nivel configurado (alias "p"), uno por artículo.
            $p = $this->precio;
            $sub = $this->db->table($p['table'])
                ->select($p['articulo'].' as articulo')
                ->selectRaw('MAX('.$this->wrap($p['precio']).') as precio')
                ->where($p['nivel_columna'], $p['nivel'])
                ->when($p['where_raw'] ?? null, fn (Builder $q, $raw) => $q->whereRaw($raw))
                ->groupBy($p['articulo']);

            $query->leftJoinSub($sub, 'p', fn ($j) => $j->whereRaw($this->unir('p')))
                ->addSelect('p.precio as precio');
        } else {
            $query->addSelect("a.{$c['precio']} as precio");
        }

        if ($this->stock['table'] ?? null) {
            $s = $this->stock;
            $sub = $this->db->table($s['table'])
                ->select($s['articulo'].' as articulo')
                ->selectRaw('SUM('.$this->wrap($s['cantidad']).') as cantidad')
                ->when($s['excluir'], fn (Builder $q, $excluir) => $q->whereNotIn($s['bodega'], $excluir))
                ->groupBy($s['articulo']);

            $query->leftJoinSub($sub, 's', fn ($j) => $j->whereRaw($this->unir('s')))
                ->selectRaw('COALESCE('.$this->wrap('s.cantidad').', 0) as existencia');
        } else {
            $query->addSelect("a.{$c['existencia']} as existencia");
        }

        if ($this->whereRaw) {
            $query->whereRaw($this->whereRaw);
        }

        return $query;
    }

    /** Condición de unión por código con una subconsulta (alias $alias, columna "articulo"). */
    private function unir(string $alias): string
    {
        $cond = $this->col('codigo').' = '.$this->wrap($alias.'.articulo');

        // COLLATE evita conflictos de intercalación entre bases distintas (PRODUCCION vs Softland).
        return $this->db->getDriverName() === 'sqlsrv' ? $cond.' COLLATE DATABASE_DEFAULT' : $cond;
    }

    /** Columna de la tabla principal, ya entrecomillada: [a].[COLUMNA] */
    private function col(string $campo): string
    {
        return $this->wrap('a.'.$this->columnas[$campo]);
    }

    private function wrap(string $columna): string
    {
        return $this->db->getQueryGrammar()->wrap($columna);
    }
}
