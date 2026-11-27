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

    /*
    | Tabla de Softland (antes: vista PRODUCCCION.dbo.GMV_Clientes, que corta
    | mal nombre y RUC). El RUC se separa del nombre en App\Clientes\Cliente
    | y el disponible = LIMITE_CREDITO - SALDO.
    */
    'table' => 'Softland.umk.CLIENTE',

    'columns' => [
        'codigo' => 'CLIENTE',
        'nombre' => 'NOMBRE',       // trae el RUC al final ("… - RUC J031…")
        'direccion' => 'DIRECCION', // trae prefijo "DETALLE: "
        'vendedor' => 'VENDEDOR',
        'moroso' => 'MOROSO',       // 'S' / 'N'
        'credito' => 'LIMITE_CREDITO',  // en la moneda del cliente (MONEDA)
        'saldo' => 'SALDO',             // en la moneda del cliente (MONEDA)
        // Datos que el pedido de Softland copia del cliente:
        'condicion_pago' => 'CONDICION_PAGO',
        'nivel_precio' => 'NIVEL_PRECIO',   // define los precios que ve el cliente
        'moneda_nivel' => 'MONEDA_NIVEL',   // L / D: moneda de esos precios
        'moneda' => 'MONEDA',               // COR / DOL: moneda del crédito
        'telefono1' => 'TELEFONO1',
        'telefono2' => 'TELEFONO2',
        'contacto' => 'CONTACTO', // "Atención" de la cotización si el pedido no trae contacto
        'fax' => 'FAX',
    ],

    // Columnas extra donde también se busca (alias "c").
    'buscar_en' => ['ALIAS'],

    // Solo clientes activos (alias "c" = umk.CLIENTE).
    'where_raw' => "c.ACTIVO = 'S'",

    /*
    | Por ahora la app trabaja para un único vendedor: solo se listan y se
    | pueden elegir sus clientes. F12 = CESIA MOREIRA (usuario Softland
    | CMOREIRA, consecutivo de pedidos DESP-A).
    */
    'vendedor' => 'F12',
    'vendedor_nombre' => 'Cesia Moreira',

    'search_limit' => 30,

];
