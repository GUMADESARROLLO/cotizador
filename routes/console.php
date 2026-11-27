<?php

use App\Catalogo\Reorden;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recalcula categoría Reorder Point y meses de inventario (~8 s en SQL Server).
// Las búsquedas también lo refrescan solas en segundo plano; esto solo lo precalienta.
Artisan::command('reorden:refrescar', function (Reorden $reorden) {
    if (! $reorden->habilitado()) {
        $this->warn('Reorder Point deshabilitado (REORDER_ENABLED o catálogo demo).');

        return 1;
    }
    $inicio = microtime(true);
    $filas = $reorden->refrescar();
    $this->info(sprintf('Reorder Point al %s: %d artículos (foto de categorías %s) en %.1f s.',
        $reorden->corte(), $filas, $reorden->fechaCategoria() ?? '—', microtime(true) - $inicio));
})->purpose('Recalcula y cachea la categoría Reorder Point y meses de inventario');

Schedule::command('reorden:refrescar')->dailyAt('06:30')->withoutOverlapping();
