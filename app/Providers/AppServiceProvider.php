<?php

namespace App\Providers;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\DemoLotes;
use App\Catalogo\Imagenes;
use App\Catalogo\Lotes;
use App\Catalogo\SqlServerCatalogo;
use App\Catalogo\SqlServerLotes;
use App\Clientes\Clientes;
use App\Clientes\DemoClientes;
use App\Clientes\SqlServerClientes;
use App\Softland\ConsecutivoPedido;
use App\Softland\EnviarPedidoSoftland;
use App\Softland\PedidoSoftland;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bind (no singleton): el nivel de precio depende del cliente de la petición
        // (lo fija el middleware RequiereCliente antes de llegar al controlador).
        $this->app->bind(Catalogo::class, function () {
            $cfg = config('articulos');

            if ($cfg['driver'] === 'demo') {
                return new DemoCatalogo;
            }

            return new SqlServerCatalogo(
                db: DB::connection($cfg['connection']),
                tabla: $cfg['table'],
                columnas: $cfg['columns'],
                whereRaw: $cfg['where_raw'],
                limite: $cfg['search_limit'],
                stock: $cfg['stock'],
                precio: $cfg['precio'],
            );
        });

        $this->app->singleton(Clientes::class, function () {
            $cfg = config('clientes');

            if ($cfg['driver'] === 'demo') {
                return new DemoClientes(vendedor: $cfg['vendedor']);
            }

            return new SqlServerClientes(
                db: DB::connection($cfg['connection']),
                tabla: $cfg['table'],
                columnas: $cfg['columns'],
                whereRaw: $cfg['where_raw'],
                limite: $cfg['search_limit'],
                vendedor: $cfg['vendedor'],
                buscarEn: $cfg['buscar_en'] ?? [],
            );
        });

        $this->app->singleton(ConsecutivoPedido::class, function () {
            $cfg = config('softland');

            return new ConsecutivoPedido(
                db: $cfg['driver'] === 'demo' ? null : DB::connection($cfg['connection']),
                esquema: $cfg['esquema'],
                codigo: $cfg['consecutivo'],
            );
        });

        // Lotes de la bodega del pedido (bind: en demo dependen del catálogo de la petición).
        $this->app->bind(Lotes::class, fn ($app) => config('softland.driver') === 'demo'
            ? new DemoLotes($app->make(Catalogo::class))
            : new SqlServerLotes(
                db: DB::connection(config('softland.connection')),
                esquema: config('softland.esquema'),
                bodega: config('softland.bodega'),
            ));

        // Arma (sin insertar) el pedido de Softland.
        $this->app->bind(PedidoSoftland::class, fn () => new PedidoSoftland(
            db: DB::connection(config('softland.connection')),
            config: config('softland'),
        ));

        // Inserción manual en Softland (botón "Enviar a Softland").
        $this->app->bind(EnviarPedidoSoftland::class, fn ($app) => new EnviarPedidoSoftland(
            db: DB::connection(config('softland.connection')),
            preparador: $app->make(PedidoSoftland::class),
            lotes: $app->make(Lotes::class),
            config: config('softland'),
        ));

        $this->app->singleton(Imagenes::class, fn ($app) => new Imagenes(
            cache: $app['cache.store'],
            db: $app['db'],
            storage: $app['filesystem'],
            config: config('articulos.imagenes'),
            carpeta: public_path('thumbs'),
            urlBase: '/thumbs',
        ));
    }

    public function boot(): void
    {
        //
    }
}
