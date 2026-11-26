<?php

namespace App\Catalogo;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

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
        foreach (preg_split('/\s+/', $termino) as $palabra) {
            $like = '%'.$this->escaparLike($palabra).'%';
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
        $codigos = array_values(array_unique(array_filter($codigos, 'strlen')));
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
            "a.{$c['precio']} as precio",
        ]);

        if ($this->stock['table'] ?? null) {
            $s = $this->stock;
            $sub = $this->db->table($s['table'])
                ->select($s['articulo'].' as articulo')
                ->selectRaw('SUM('.$this->wrap($s['cantidad']).') as cantidad')
                ->when($s['excluir'], fn (Builder $q, $excluir) => $q->whereNotIn($s['bodega'], $excluir))
                ->groupBy($s['articulo']);

            // COLLATE evita conflictos de intercalación entre bases distintas (p. ej. PRODUCCION vs Softland).
            $join = $this->db->getDriverName() === 'sqlsrv'
                ? $this->col('codigo').' = '.$this->wrap('s.articulo').' COLLATE DATABASE_DEFAULT'
                : $this->col('codigo').' = '.$this->wrap('s.articulo');

            $query->leftJoinSub($sub, 's', fn ($j) => $j->whereRaw($join))
                ->selectRaw('COALESCE('.$this->wrap('s.cantidad').', 0) as existencia');
        } else {
            $query->addSelect("a.{$c['existencia']} as existencia");
        }

        if ($this->whereRaw) {
            $query->whereRaw($this->whereRaw);
        }

        return $query;
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

    private function escaparLike(string $valor): string
    {
        return Str::of($valor)
            ->replace('!', '!!')
            ->replace('%', '!%')
            ->replace('_', '!_')
            ->replace('[', '![')
            ->toString();
    }
}
