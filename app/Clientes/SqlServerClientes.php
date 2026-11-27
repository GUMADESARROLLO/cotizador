<?php

namespace App\Clientes;

use App\Support\Orden;
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
        if (SqlLike::palabras($termino) === []) {
            return [];
        }

        return $this->filtrarTexto($this->base(), $termino)
            ->orderByRaw("CASE WHEN {$this->col('codigo')} = ? THEN 0 ELSE 1 END", [trim($termino)])
            ->orderByRaw($this->col('nombre'))
            ->limit($limite ?? $this->limite)
            ->get()
            ->map(Cliente::fromRow(...))
            ->all();
    }

    public function listar(string $q, string $filtro, int $pagina, int $porPagina, ?Orden $orden = null): array
    {
        $total = (int) $this->filtrar($this->consulta(), $q, $filtro)->count();

        $query = $this->filtrar($this->base(), $q, $filtro);
        $expr = $orden ? $this->expresionOrden($orden->campo) : null;
        if ($expr !== null) {
            // Columna elegida al tocar el encabezado; vacíos (NULL) al final.
            $query->orderByRaw("CASE WHEN {$expr} IS NULL THEN 1 ELSE 0 END")
                ->orderByRaw("{$expr} {$orden->dir()}");
        } elseif (SqlLike::palabras($q) !== []) {
            // La coincidencia exacta de código va primero.
            $query->orderByRaw("CASE WHEN {$this->col('codigo')} = ? THEN 0 ELSE 1 END", [trim($q)]);
        }

        // Desempate estable para paginar; SQL Server no admite repetir la columna elegida.
        foreach ([$this->col('nombre'), $this->col('codigo')] as $desempate) {
            if ($desempate !== $expr) {
                $query->orderByRaw($desempate);
            }
        }

        $items = $query
            ->forPage(max(1, $pagina), $porPagina) // OFFSET … FETCH NEXT en SQL Server
            ->get()
            ->map(Cliente::fromRow(...))
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    /** Expresión SQL de un campo ordenable (lista cerrada: nada del usuario llega al SQL). */
    private function expresionOrden(string $campo): ?string
    {
        $credito = 'COALESCE('.$this->col('credito').', 0)';
        $saldo = 'COALESCE('.$this->col('saldo').', 0)';

        return match ($campo) {
            'cliente' => $this->col('nombre'),
            'nivel' => $this->col('nivel_precio'),
            'limite' => $credito,
            'saldo' => $saldo,
            'disponible' => "({$credito} - {$saldo})",
            'telefonos' => "NULLIF(LTRIM(RTRIM({$this->col('telefono1')})), '')",
            default => null,
        };
    }

    public function contar(string $q): array
    {
        [$moroso, $saldo] = [$this->col('moroso'), $this->col('saldo')];

        $fila = $this->filtrarTexto($this->consulta(), $q)
            ->selectRaw(
                'COUNT(*) as todos, '
                ."COALESCE(SUM(CASE WHEN {$moroso} = 'S' THEN 1 ELSE 0 END), 0) as morosos, "
                ."COALESCE(SUM(CASE WHEN COALESCE({$saldo}, 0) <> 0 THEN 1 ELSE 0 END), 0) as saldo"
            )
            ->first();

        return ['todos' => (int) $fila->todos, 'morosos' => (int) $fila->morosos, 'saldo' => (int) $fila->saldo];
    }

    public function encontrar(string $codigo): ?Cliente
    {
        $row = $this->base()->where('c.'.$this->columnas['codigo'], trim($codigo))->first();

        return $row ? Cliente::fromRow($row) : null;
    }

    /** Cada palabra de q debe aparecer en el código, el nombre (que trae el RUC), el RUC o el alias. */
    private function filtrarTexto(Builder $query, string $q): Builder
    {
        $donde = [$this->col('codigo'), $this->col('nombre')];
        if (isset($this->columnas['ruc'])) {
            $donde[] = $this->col('ruc');
        }
        foreach ($this->buscarEn as $extra) {
            $donde[] = $this->db->getQueryGrammar()->wrap('c.'.$extra);
        }

        foreach (SqlLike::palabras($q) as $palabra) {
            $like = SqlLike::contiene($palabra);
            $query->where(function (Builder $sub) use ($donde, $like) {
                foreach ($donde as $col) {
                    $sub->orWhereRaw("{$col} LIKE ? ESCAPE '!'", [$like]);
                }
            });
        }

        return $query;
    }

    /** Texto + segmento (todos | morosos | saldo). */
    private function filtrar(Builder $query, string $q, string $filtro): Builder
    {
        $this->filtrarTexto($query, $q);

        if ($filtro === 'morosos') {
            $query->whereRaw("{$this->col('moroso')} = 'S'");
        } elseif ($filtro === 'saldo') {
            $query->whereRaw("COALESCE({$this->col('saldo')}, 0) <> 0");
        }

        return $query;
    }

    private function base(): Builder
    {
        $select = [];
        foreach ($this->columnas as $campo => $columna) {
            $select[] = "c.{$columna} as {$campo}";
        }

        return $this->consulta()->select($select);
    }

    /** Tabla con el filtro de activos y de vendedor, sin columnas. */
    private function consulta(): Builder
    {
        $query = $this->db->table($this->tabla.' as c');

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
