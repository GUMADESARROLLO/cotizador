<?php

/*
|--------------------------------------------------------------------------
| Catálogo de artículos (SQL Server, solo lectura)
|--------------------------------------------------------------------------
|
| La app nunca escribe en SQL Server: solo busca artículos y lee su
| existencia. Estos valores son fijos para Unimark (Softland, empresa UMK).
|
| driver: "sqlsrv" para la base real, "demo" para datos de ejemplo en memoria
|         (útil para probar la interfaz sin conexión y en los tests).
|
*/

return [

    'driver' => env('ARTICULOS_DRIVER', 'sqlsrv'),

    'connection' => env('DB_SQL_HOST_CONNECTION', 'sqlsrv'),

    // Vista de artículos con descripción y precios.
    'table' => 'PRODUCCION.dbo.iweb_articulos',

    'columns' => [
        'codigo' => 'ARTICULO',
        'descripcion' => 'DESCRIPCION',
        'unidad' => 'UNIDAD_ALMACEN',
        'precio' => 'PRECIO_FARMACIA',
        // Solo se usa si no hay tabla de existencias (abajo). En la vista es solo la bodega 002.
        'existencia' => 'total',
    ],

    /*
    | Existencia real: SUM(CANT_DISPONIBLE) por artículo en Softland UMK,
    | excluyendo la bodega 004 (dañados y vencidos).
    */
    'stock' => [
        'table' => 'Softland.umk.EXISTENCIA_BODEGA',
        'articulo' => 'ARTICULO',
        'cantidad' => 'CANT_DISPONIBLE',
        'bodega' => 'BODEGA',
        'excluir' => ['004'],
    ],

    // Solo artículos con precio FARMACIA (alias "a" = tabla principal).
    'where_raw' => 'a.PRECIO_FARMACIA > 0',

    'search_limit' => 30,

    'currency' => 'C$',

];
