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
    'table' => 'PRODUCCCION.dbo.iweb_articulos',

    'columns' => [
        'codigo' => 'ARTICULO',
        'descripcion' => 'DESCRIPCION',
        'unidad' => 'UNIDAD_ALMACEN',
        // Solo se usa si no hay nivel de precio configurado (abajo).
        'precio' => 'PRECIO_FARMACIA',
        // Solo se usa si no hay tabla de existencias (abajo). En la vista es solo la bodega 002.
        'existencia' => 'total',
    ],

    /*
    | Precio: nivel INST.PUB. C$ vigente, de la misma fuente que SAC
    | (PRODUCCION.dbo.iweb_precio / sp_iweb_precios).
    | Ojo: en Softland la mayoría de artículos tiene este nivel en C$1.00.
    */
    'precio' => [
        'table' => 'PRODUCCCION.dbo.iweb_precio',
        'articulo' => 'ARTICULO',
        'precio' => 'PRECIO',
        'nivel_columna' => 'NIVEL_PRECIO',
        'nivel' => 'INST.PUB. C$',
        'where_raw' => "VIGENCIA = 'S'",
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

    // Solo artículos que tienen precio en el nivel (alias "p" = precio, "a" = vista).
    'where_raw' => 'p.precio > 0',

    'search_limit' => 30,

    /*
    | Fotos de productos (mismo origen que SAC): el nombre del archivo sale
    | de db_preventa_umk.tbl_product y el original está en S3 en product/<archivo>.
    | Se sirven como miniaturas WebP en public/thumbs (ver App\Catalogo\Imagenes).
    | Se desactivan solas si no hay credenciales S3.
    */
    'imagenes' => [
        'connection' => 'mysql_preventa',
        'table' => 'tbl_product',
        'sku' => 'product_sku',
        'archivo' => 'product_image',
        'disk' => 's3',
        'prefijo' => 'product/',
        'mapa_minutos' => 60, // cache del mapa código → archivo
        'miniatura_px' => 160, // lado mayor (se muestra a 64–80 px en pantallas 2x)
        'miniatura_calidad' => 80,
    ],

    'currency' => 'C$',

];
