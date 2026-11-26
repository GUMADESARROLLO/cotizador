<?php

namespace App\Providers;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\Imagenes;
use App\Catalogo\SqlServerCatalogo;
use App\Clientes\Clientes;
use App\Clientes\DemoClientes;
use App\Clientes\SqlServerClientes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Catalogo::class, function () {
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
                return new DemoClientes;
            }

            return new SqlServerClientes(
                db: DB::connection($cfg['connection']),
                tabla: $cfg['table'],
                columnas: $cfg['columns'],
                whereRaw: $cfg['where_raw'],
                limite: $cfg['search_limit'],
            );
        });

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
