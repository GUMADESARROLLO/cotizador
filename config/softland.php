<?php

/*
|--------------------------------------------------------------------------
| Pedido en Softland (empresa UMK)
|--------------------------------------------------------------------------
|
| Datos con los que el pedido se registrará en Softland.umk.PEDIDO.
| Por ahora un único vendedor: F12 (Cesia Moreira), usuario CMOREIRA.
| El número de pedido sale de umk.CONSECUTIVO_FA: VALOR_CONSECUTIVO guarda
| el ÚLTIMO número usado, el siguiente es ese valor + 1.
|
*/

return [

    // Mismo modo que el catálogo: "demo" no consulta Softland.
    'driver' => env('ARTICULOS_DRIVER', 'sqlsrv'),

    'connection' => env('DB_SQL_HOST_CONNECTION', 'sqlsrv'),

    'esquema' => 'Softland.umk',

    'usuario' => 'CMOREIRA',

    // Vendedor del pedido (el mismo de config/clientes.php → 'vendedor').
    'vendedor' => 'F12',

    /*
    | COBRADOR del pedido: siempre el mismo vendedor, NO el de la ficha del
    | cliente (en Softland 12 de los 49 clientes de F12 tienen ND o F02, pero
    | los 445 pedidos de F12 del último año llevan COBRADOR = F12).
    */
    'cobrador' => 'F12',

    // RUTA y ZONA del pedido: fijas en ND ("NO DEFINIDA" en los catálogos umk.RUTA / umk.ZONA).
    'ruta' => 'ND',
    'zona' => 'ND',

    // BODEGA del pedido y de sus líneas (la existencia que se muestra sale de la
    // misma: config/articulos.php → stock.incluir).
    'bodega' => '006',

    // PAIS del pedido: siempre NI (algunas fichas de cliente traen "505", no se copia).
    'pais' => 'NI',

    /*
    | Lo que se copia de la ficha del cliente (umk.CLIENTE), igual que hace
    | Softland en los 445 pedidos de F12 del último año:
    |   CONDICION_PAGO, NIVEL_PRECIO (y con él la moneda: MONEDA_NIVEL L/D).
    */

    // Hasta cuántos días adelante se puede poner la fecha del pedido (calendario).
    'fecha_max_dias' => 60,

    // Minutos tras los que un envío que quedó en "enviando" se revisa contra Softland.
    'envio_trabado_minutos' => 2,

    // Símbolo por moneda de Softland (L = local, D = dólar).
    'monedas' => ['L' => 'C$', 'D' => 'US$'],

    // Consecutivo de pedidos de CMOREIRA (umk.CONSECUFA_USUARIO).
    'consecutivo' => 'DESP-A',

];
