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

    /*
    | Tabla de artículos de Softland (antes: vista PRODUCCCION.dbo.iweb_articulos).
    | Misma selección que la vista, verificada fila por fila (1,745 artículos):
    | activos, código de 8 caracteres (excluye -M muestras, -B, códigos viejos de
    | 7 dígitos) y que no sean de clasificación 7 = Servicios.
    */
    'table' => 'Softland.umk.ARTICULO',

    'columns' => [
        'codigo' => 'ARTICULO',
        'descripcion' => 'DESCRIPCION',
        'unidad' => 'UNIDAD_ALMACEN',
        // Empaque: FACTOR_EMPAQUE unidades (viales, tabletas…) de UNIDAD_EMPAQUE por caja.
        // Ej. 13705183: 50 VIA por CJA → 2,800 viales = 56 cajas.
        'factor' => 'FACTOR_EMPAQUE',
        'unidad_empaque' => 'UNIDAD_EMPAQUE',
        // Solo se usan si no hay nivel de precio / tabla de existencias (abajo).
        'precio' => 'PRECIO_BASE_LOCAL',
        'existencia' => 'EXISTENCIA_MINIMA',
    ],

    /*
    | Precio vigente del nivel, igual que la vista iweb_precio:
    | ARTICULO_PRECIO de la versión activa (VERSION_NIVEL.ESTADO = 'A') y con
    | la fecha de hoy dentro de FECHA_INICIO..FECHA_FIN.
    | "nivel" es el valor por defecto: con un cliente elegido se usa SU nivel
    | (NIVEL_PRECIO de umk.CLIENTE), ver App\Http\Middleware\RequiereCliente.
    | Ojo: en Softland la mayoría de artículos tiene INST.PUB. C$ en C$1.00.
    */
    'precio' => [
        'table' => 'Softland.umk.ARTICULO_PRECIO',
        'version_table' => 'Softland.umk.VERSION_NIVEL',
        'articulo' => 'ARTICULO',
        'precio' => 'PRECIO',
        'nivel_columna' => 'NIVEL_PRECIO',
        'nivel' => 'INST.PUB. C$',
        'where_raw' => "vn.ESTADO = 'A' AND GETDATE() BETWEEN ap.FECHA_INICIO AND ap.FECHA_FIN",
    ],

    /*
    | Existencia real: SUM(CANT_DISPONIBLE) por artículo en Softland UMK.
    | Por ahora solo la bodega 006, la que usan los pedidos de F12 (y la que
    | llevará el pedido al insertarse en Softland). "incluir" manda sobre
    | "excluir"; con "incluir" vacío se suman todas menos las excluidas.
    */
    'stock' => [
        'table' => 'Softland.umk.EXISTENCIA_BODEGA',
        'articulo' => 'ARTICULO',
        'cantidad' => 'CANT_DISPONIBLE',
        'bodega' => 'BODEGA',
        'incluir' => ['006'],
        'excluir' => ['004'],
    ],

    // Selección de artículos (alias "a" = umk.ARTICULO).
    'where_raw' => "a.ACTIVO = 'S' AND LEN(a.ARTICULO) = 8 AND ISNULL(a.CLASIFICACION_1, '') <> '7'",

    // Se muestran también los que no tienen precio en el nivel del cliente:
    // el vendedor pone el precio a mano en el pedido.

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

    /*
    | Reorder Point de GUMA@NET: categoría (A, B, C, L; sufijo CA = Cruz Azul) y
    | meses de inventario (inventario 002+005+006 / promedio mensual privado).
    | Sale de PRODUCCION.dbo.fn_reorder_meses_inventario(@CORTE) en el SQL Server
    | de GUMA@NET (192.168.1.18), distinto al del catálogo: conexión propia.
    | Tarda ~8 s, así que se cachea completo y se refresca en segundo plano
    | (ver App\Catalogo\Reorden); la búsqueda nunca la espera.
    |
    | fecha_corte: vacía = hoy (usa la última foto de categorías <= hoy). Se fija
    | cuando la última foto no es confiable (la del 24-nov-2026 está incompleta;
    | la validada es la del 26-oct-2026). El inventario siempre es el actual.
    */
    'reorden' => [
        'habilitado' => (bool) env('REORDER_ENABLED', true),
        'connection' => env('REORDER_CONNECTION', 'sqlsrv_reorder'),
        'funcion' => env('REORDER_FUNCION', 'PRODUCCION.dbo.fn_reorder_meses_inventario'),
        'fecha_corte' => env('REORDER_FECHA_CORTE'),
        'refrescar_minutos' => (int) env('REORDER_REFRESCAR_MINUTOS', 120),
        'reintento_minutos' => (int) env('REORDER_REINTENTO_MINUTOS', 10),
    ],

    'currency' => 'C$',

];
