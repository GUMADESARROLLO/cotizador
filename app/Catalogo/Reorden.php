<?php

namespace App\Catalogo;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Categoría del Reorder Point (GUMA@NET) y meses de inventario por artículo.
 *
 * Los datos salen de PRODUCCION.dbo.fn_reorder_meses_inventario(@CORTE), que
 * tarda ~8 s (lee 13 meses de ventas) y devuelve solo ~165 filas. Por eso
 * NUNCA se consulta dentro de una búsqueda:
 *
 *  - El resultado completo se guarda en caché como un mapa código => datos.
 *  - La búsqueda solo cruza sus códigos contra ese mapa (costo ~0).
 *  - Si el mapa venció (o no existe aún), se refresca DESPUÉS de enviar la
 *    respuesta (defer) y mientras tanto se usa el anterior; con un candado
 *    para que solo una petición lo recalcule.
 *  - Si la consulta falla, se registra en el log y no se reintenta hasta
 *    pasado "reintento_minutos". La búsqueda nunca se rompe por esto.
 *
 * `php artisan reorden:refrescar` lo recalcula a mano (también programado).
 */
class Reorden
{
    private const CLAVE = 'reorden_mapa';

    /** @var array{creado:int, corte:string, fecha_categoria:?string, mapa:array<string, array{categoria:?string, meses:?float}>}|null */
    private ?array $memo = null;

    /**
     * @param  array{habilitado:bool, connection:string, funcion:string, fecha_corte:?string, refrescar_minutos:int, reintento_minutos:int}  $config
     */
    public function __construct(
        private Cache $cache,
        private ConnectionResolverInterface $db,
        private array $config,
    ) {}

    public function habilitado(): bool
    {
        return (bool) $this->config['habilitado'];
    }

    /**
     * Datos de los códigos pedidos. Solo incluye los que están en la foto de
     * categorías; si el mapa aún no está cargado devuelve [] (la vista lo
     * distingue con cargado()).
     *
     * @param  list<string>  $codigos
     * @return array<string, array{categoria:?string, meses:?float}>
     */
    public function para(array $codigos): array
    {
        $mapa = $this->datos()['mapa'] ?? [];

        $resultado = [];
        foreach ($codigos as $codigo) {
            if (isset($mapa[self::norm($codigo)])) {
                $resultado[$codigo] = $mapa[self::norm($codigo)];
            }
        }

        return $resultado;
    }

    /** ¿Hay un mapa cargado? (si no, la vista no muestra nada en lugar de "—"). */
    public function cargado(): bool
    {
        return $this->datos() !== null;
    }

    /** Fecha de la foto de categorías usada (Y-m-d), para el tooltip. */
    public function fechaCategoria(): ?string
    {
        return $this->datos()['fecha_categoria'] ?? null;
    }

    /** Fecha de corte con la que se calcula: la configurada o la de hoy. */
    public function corte(): string
    {
        return filled($this->config['fecha_corte'] ?? null)
            ? (string) $this->config['fecha_corte']
            : now()->toDateString();
    }

    /**
     * Ejecuta la función en SQL Server y guarda el mapa. Lanza la excepción
     * si falla (el llamador decide si registrarla).
     *
     * @return int filas cargadas
     */
    public function refrescar(): int
    {
        $corte = $this->corte();

        $filas = $this->db->connection($this->config['connection'])
            ->select("SELECT ARTICULO, CATEGORIA, MESES_INVENTARIO, FECHA_CATEGORIA FROM {$this->config['funcion']}(?)", [$corte]);

        $mapa = [];
        $fechaCategoria = null;
        foreach ($filas as $f) {
            $categoria = trim((string) $f->CATEGORIA);
            $mapa[self::norm((string) $f->ARTICULO)] = [
                'categoria' => $categoria !== '' ? $categoria : null,
                'meses' => $f->MESES_INVENTARIO === null ? null : round((float) $f->MESES_INVENTARIO, 1),
            ];
            $fechaCategoria ??= $f->FECHA_CATEGORIA ? substr((string) $f->FECHA_CATEGORIA, 0, 10) : null;
        }

        // Solo arreglos (la caché no deserializa clases). Se guarda más tiempo del que
        // tarda en vencer: así siempre hay un mapa anterior que mostrar mientras se refresca.
        $this->memo = [
            'creado' => now()->getTimestamp(),
            'corte' => $corte,
            'fecha_categoria' => $fechaCategoria,
            'mapa' => $mapa,
        ];
        $this->cache->put(self::CLAVE, $this->memo, now()->addDays(3));

        return count($mapa);
    }

    /** Mapa en caché (o null), programando un refresco en segundo plano si hace falta. */
    private function datos(): ?array
    {
        if (! $this->habilitado()) {
            return null;
        }
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            $datos = $this->cache->get(self::CLAVE);
        } catch (Throwable $e) {
            Log::warning('Reorder Point: caché no disponible: '.$e->getMessage());

            return null;
        }

        $datos = is_array($datos) ? $datos : null;
        $vencido = $datos === null
            || ($datos['corte'] ?? null) !== $this->corte()
            || now()->getTimestamp() - ($datos['creado'] ?? 0) >= $this->config['refrescar_minutos'] * 60;

        if ($vencido) {
            defer(fn () => $this->refrescarEnSegundoPlano(), 'reorden-refrescar');
        }

        // Un mapa de otra fecha de corte no se muestra (sería de otra configuración).
        if ($datos !== null && ($datos['corte'] ?? null) !== $this->corte()) {
            $datos = null;
        }

        return $this->memo = $datos;
    }

    private function refrescarEnSegundoPlano(): void
    {
        $store = $this->cache->getStore();
        // El candado dura lo mismo que la espera tras un error: si la consulta falla
        // no se libera, y nadie reintenta hasta que venza.
        $lock = $store instanceof LockProvider
            ? $store->lock('reorden_refresco', $this->config['reintento_minutos'] * 60)
            : null;

        if ($lock && ! $lock->get()) {
            return; // otra petición ya lo está calculando (o falló hace poco)
        }

        try {
            $this->refrescar();
            $lock?->release();
        } catch (Throwable $e) {
            Log::warning('Reorder Point no disponible: '.$e->getMessage());
        }
    }

    /** SQL Server ignora espacios finales y mayúsculas: se normaliza igual. */
    private static function norm(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }
}
