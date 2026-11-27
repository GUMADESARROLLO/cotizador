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

    // Fecha de vencimiento de las líneas sin lote guardado: el lote que vence primero con
    // existencia en estas bodegas (las del inventario del Reorder Point; sin la 004).
    'bodegas_vence' => array_filter(explode(',', (string) env('COTIZACION_BODEGAS_VENCE', '006,002,005'))),

    // Documentos adjuntos a un pedido ya ingresado en Softland (botón "Adjuntar").
    // Se guardan en el bucket, en <carpeta>/<N.º de pedido Softland>/.
    'adjuntos' => [
        'disk' => env('ADJUNTOS_DISK', 's3'),
        'carpeta' => env('ADJUNTOS_CARPETA', 'Cotizaciones'),
        'max_kb' => (int) env('ADJUNTOS_MAX_KB', 20480), // 20 MB por archivo
        'extensiones' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'msg', 'eml'],
    ],

    // Columna "Tiempo de Entrega" de cada artículo.
    'tiempo_entrega' => env('COTIZACION_TIEMPO_ENTREGA', 'INMEDIATO'),

];
