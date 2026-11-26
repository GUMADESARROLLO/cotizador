<?php

namespace App\Providers;

use App\Catalogo\Catalogo;
use App\Catalogo\DemoCatalogo;
use App\Catalogo\SqlServerCatalogo;
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
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
