<?php

/*
|--------------------------------------------------------------------------
| Clientes (SQL Server, solo lectura)
|--------------------------------------------------------------------------
|
| Antes de buscar artículos se elige el cliente del pedido.
| Valores fijos para Unimark. Usa el mismo modo (sqlsrv/demo) que el catálogo.
|
*/

return [

    'driver' => env('ARTICULOS_DRIVER', 'sqlsrv'),

    'connection' => env('DB_SQL_HOST_CONNECTION', 'sqlsrv'),

    'table' => 'PRODUCCCION.dbo.GMV_Clientes',

    'columns' => [
        'codigo' => 'CLIENTE',
        'nombre' => 'NOMBRE',
        'direccion' => 'DIRECCION',
        'ruc' => 'RUC',
        'vendedor' => 'VENDEDOR',
        'moroso' => 'MOROSO',       // 'S' / 'N'
        'credito' => 'CREDITO',
        'saldo' => 'SALDO',
        'disponible' => 'DISPONIBLE',
    ],

    // Solo clientes activos (alias "c" = la vista).
    'where_raw' => "c.ACTIVO = 'S'",

    'search_limit' => 30,

];
