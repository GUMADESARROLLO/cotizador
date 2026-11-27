<?php

/*
|--------------------------------------------------------------------------
| Cotización en Excel (detalle del pedido)
|--------------------------------------------------------------------------
|
| Se genera sobre la plantilla oficial (COTIZACION CA-158-10-2026.xls,
| convertida a .xlsx sin cambios) para respetar logo, recuadros, dirección,
| estilos y configuración de impresión. Ver App\Cotizaciones\CotizacionExcel.
|
*/

return [

    'plantilla' => resource_path('plantillas/cotizacion.xlsx'),

    // Firma de la izquierda (en la plantilla: "Lic. Cesia Moreira").
    // Vacía = "Lic. " + clientes.vendedor_nombre.
    'firma' => env('COTIZACION_FIRMA'),

    // Columna "Tiempo de Entrega" de cada artículo.
    'tiempo_entrega' => env('COTIZACION_TIEMPO_ENTREGA', 'INMEDIATO'),

];
