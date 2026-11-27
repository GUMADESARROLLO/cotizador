<?php

namespace App\Catalogo;

use App\Support\Orden;
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
     * @param  array{table:?string,articulo:string,cantidad:string,bodega:string,incluir?:list<string>,excluir?:list<string>}|null  $stock
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
        $query = $this->filtrar($this->base(), $termino);

        // Coincidencia exacta de código primero, luego por descripción.
        $query->orderByRaw("CASE WHEN {$codigo} = ? THEN 0 ELSE 1 END", [$termino])
            ->orderByRaw($this->col('descripcion'));

        return $query->limit($limite ?? $this->limite)
            ->get()
            ->map(Articulo::fromRow(...))
            ->all();
    }

    public function listar(string $q, string $filtro, int $pagina, int $porPagina, array $codigosPrioridad = [], ?Orden $orden = null, array $valoresOrden = []): array
    {
        $q = trim($q);
        $pagina = max(1, $pagina);
        $porPagina = max(1, $porPagina);

        $query = $this->base();
        if ($q !== '') {
            $this->filtrar($query, $q);
        }

        if ($filtro === 'existencia') {
            $query->whereRaw(($this->stock['table'] ?? null)
                ? 'COALESCE('.$this->wrap('s.cantidad').', 0) > 0'
                : $this->wrap('a.'.$this->columnas['existencia']).' > 0');
        } elseif ($filtro === 'prioridad') {
            // Siempre como texto (ver encontrarVarios); sin códigos, no hay nada que mostrar.
            $codigos = array_values(array_unique(array_map('strval', $codigosPrioridad)));
            $codigos === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('a.'.$this->columnas['codigo'], $codigos);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        [$expr, $bindings] = $orden ? $this->expresionOrden($orden->campo, $valoresOrden) : [null, []];
        if ($expr !== null) {
            // Columna elegida por el usuario; sin valor (NULL) siempre al final.
            $query->orderByRaw("CASE WHEN {$expr} IS NULL THEN 1 ELSE 0 END", $bindings)
                ->orderByRaw("{$expr} {$orden->dir()}", $bindings);
        } elseif ($q !== '') {
            // Con búsqueda: coincidencia exacta de código primero.
            $query->orderByRaw('CASE WHEN '.$this->col('codigo').' = ? THEN 0 ELSE 1 END', [$q]);
        }
        // Siempre: descripción y código (orden estable para paginar). SQL Server no admite
        // repetir una columna en el ORDER BY: se omite la que ya es la elegida.
        foreach ([$this->col('descripcion'), $this->col('codigo')] as $desempate) {
            if ($desempate !== $expr) {
                $query->orderByRaw($desempate);
            }
        }

        $items = $query->offset(($pagina - 1) * $porPagina)->limit($porPagina)
            ->get()
            ->map(Articulo::fromRow(...))
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Expresión SQL (ya entrecomillada) y bindings de un campo ordenable; [null, []] si no aplica.
     * categoria/meses vienen de fuera (Reorder Point): CASE código WHEN … THEN valor.
     *
     * @param  array<string, string|float|null>  $valores
     * @return array{0: ?string, 1: list<mixed>}
     */
    private function expresionOrden(string $campo, array $valores): array
    {
        $c = $this->columnas;

        return match ($campo) {
            'articulo' => [$this->col('descripcion'), []],
            'codigo' => [$this->col('codigo'), []],
            'existencia' => [($this->stock['table'] ?? null)
                ? 'COALESCE('.$this->wrap('s.cantidad').', 0)'
                : $this->wrap('a.'.$c['existencia']), []],
            'precio' => [($this->precio['table'] ?? null)
                ? 'COALESCE('.$this->wrap('p.precio').', 0)'
                : $this->wrap('a.'.$c['precio']), []],
            'categoria', 'meses' => $this->casePorCodigo($valores, $campo === 'meses'),
            default => [null, []],
        };
    }

    /** @return array{0: ?string, 1: list<mixed>} */
    private function casePorCodigo(array $valores, bool $numerico): array
    {
        $valores = array_filter($valores, fn ($v) => $v !== null && $v !== '');
        if ($valores === []) {
            return [null, []];
        }

        $sql = 'CASE '.$this->col('codigo');
        $bindings = [];
        foreach ($valores as $codigo => $valor) {
            // CAST: si no, SQL Server compara el número como texto ("10.6" < "9.0").
            $sql .= $numerico ? ' WHEN ? THEN CAST(? AS FLOAT)' : ' WHEN ? THEN ?';
            array_push($bindings, (string) $codigo, $numerico ? (float) $valor : (string) $valor);
        }

        return [$sql.' ELSE NULL END', $bindings];
    }

    /** Cada palabra debe aparecer en el código o en la descripción. */
    private function filtrar(Builder $query, string $termino): Builder
    {
        $codigo = $this->col('codigo');
        $descripcion = $this->col('descripcion');

        foreach (SqlLike::palabras($termino) as $palabra) {
            $like = SqlLike::contiene($palabra);
            $query->where(function (Builder $q) use ($codigo, $descripcion, $like) {
                $q->whereRaw("{$codigo} LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("{$descripcion} LIKE ? ESCAPE '!'", [$like]);
            });
        }

        return $query;
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

        // SQL Server admite como máximo 2,100 parámetros por consulta: se consulta por bloques.
        $resultado = [];
        foreach (array_chunk($codigos, 1000) as $bloque) {
            $this->base()
                ->whereIn('a.'.$this->columnas['codigo'], $bloque)
                ->get()
                ->each(function ($row) use (&$resultado) {
                    $a = Articulo::fromRow($row);
                    $resultado[$a->codigo] = $a;
                });
        }

        return $resultado;
    }

    private function base(): Builder
    {
        $c = $this->columnas;

        $query = $this->db->table($this->tabla.' as a')->select([
            "a.{$c['codigo']} as codigo",
            "a.{$c['descripcion']} as descripcion",
            "a.{$c['unidad']} as unidad",
        ]);

        // Empaque (opcional): unidades internas por caja.
        if ($c['factor'] ?? null) {
            $query->addSelect("a.{$c['factor']} as factor");
        }
        if ($c['unidad_empaque'] ?? null) {
            $query->addSelect("a.{$c['unidad_empaque']} as unidad_empaque");
        }

        if ($this->precio['table'] ?? null) {
            // Precio del nivel configurado (alias "p"), uno por artículo.
            $p = $this->precio;
            $sub = $this->db->table($p['table'].' as ap')
                ->select('ap.'.$p['articulo'].' as articulo')
                ->selectRaw('MAX('.$this->wrap('ap.'.$p['precio']).') as precio')
                ->where('ap.'.$p['nivel_columna'], $p['nivel'])
                // Versión de la lista de precios (alias "vn"), como en Softland.
                ->when($p['version_table'] ?? null, fn (Builder $q, $vt) => $q->join($vt.' as vn', function ($j) {
                    $j->on('vn.NIVEL_PRECIO', '=', 'ap.NIVEL_PRECIO')
                        ->on('vn.MONEDA', '=', 'ap.MONEDA')
                        ->on('vn.VERSION', '=', 'ap.VERSION');
                }))
                ->when($p['where_raw'] ?? null, fn (Builder $q, $raw) => $q->whereRaw($raw))
                ->groupBy('ap.'.$p['articulo']);

            $query->leftJoinSub($sub, 'p', fn ($j) => $j->whereRaw($this->unir('p')))
                // Sin precio en el nivel también se muestra (precio 0): el vendedor lo pone a mano.
                ->selectRaw('COALESCE('.$this->wrap('p.precio').', 0) as precio');
        } else {
            $query->addSelect("a.{$c['precio']} as precio");
        }

        if ($this->stock['table'] ?? null) {
            $s = $this->stock;
            $sub = $this->db->table($s['table'])
                ->select($s['articulo'].' as articulo')
                ->selectRaw('SUM('.$this->wrap($s['cantidad']).') as cantidad')
                ->when(
                    $s['incluir'] ?? [],
                    fn (Builder $q, $incluir) => $q->whereIn($s['bodega'], $incluir),
                    fn (Builder $q) => $q->when($s['excluir'] ?? [], fn (Builder $q, $excluir) => $q->whereNotIn($s['bodega'], $excluir)),
                )
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
