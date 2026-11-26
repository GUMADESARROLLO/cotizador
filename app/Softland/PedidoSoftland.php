<?php

namespace App\Softland;

use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/**
 * Arma el encabezado que llevaría el pedido en Softland.umk.PEDIDO.
 *
 * SOLO PREPARA LOS DATOS: no inserta nada. Las reglas salen del análisis de
 * los pedidos de F12 (445 del último año) y del pedido pendiente 00007807
 * (CMOREIRA → 03164), que se usó como modelo.
 *
 * Cada campo se devuelve con su origen para poder revisarlo.
 */
class PedidoSoftland
{
    public function __construct(
        private ConnectionInterface $db,
        private array $config, // config/softland.php
    ) {}

    /** @var array<string, object> datos fiscales/contables por artículo (cache de la instancia) */
    private array $fiscal = [];

    /**
     * Encabezado y líneas del pedido, listos para revisar (no se inserta nada).
     *
     * @param  array<array-key, array{codigo:string,precio:float,cantidad:float,lote?:?string}>  $items  del carrito
     * @return array{encabezado: array<string, array{valor:mixed, origen:string}>, lineas: list<array<string, array{valor:mixed, origen:string}>>}
     */
    public function preparar(string $numero, string $clienteCodigo, array $items, CarbonInterface $ahora, ?string $observaciones = null): array
    {
        return [
            'encabezado' => $this->encabezado($numero, $clienteCodigo, $items, $ahora, $observaciones),
            'lineas' => $this->lineas($numero, $items, $ahora),
        ];
    }

    /**
     * @param  array<array-key, array{codigo:string,precio:float,cantidad:float}>  $items  del carrito
     * @return array<string, array{valor:mixed, origen:string}>
     */
    public function encabezado(string $numero, string $clienteCodigo, array $items, CarbonInterface $ahora, ?string $observaciones = null): array
    {
        $c = $this->fichaCliente($clienteCodigo);
        $cfg = $this->config;
        $hoy = $ahora->copy()->startOfDay()->format('Y-m-d H:i:s.v');
        $ahoraTxt = $ahora->format('Y-m-d H:i:s.v');
        $usuario = $this->usuarioAuditoria();

        $mercaderia = round(array_sum(array_map(fn ($i) => $i['precio'] * $i['cantidad'], $items)), 2);
        $unidades = array_sum(array_column($items, 'cantidad'));
        $moneda = strtoupper(trim((string) $c->MONEDA_NIVEL)) === 'D' ? 'D' : 'L';

        // IVA: % del código de impuesto de cada artículo (umk.IMPUESTO). EXE / ND = 0 %.
        $fiscal = $this->datosFiscales(array_map(fn ($i) => (string) $i['codigo'], $items));
        $impuesto = 0.0;
        $baseGravada = 0.0;
        foreach ($items as $i) {
            $porc = (float) ($fiscal[(string) $i['codigo']]->PORC ?? 0);
            if ($porc > 0) {
                $sub = $i['precio'] * $i['cantidad'];
                $impuesto += $sub * $porc / 100;
                $baseGravada += $sub;
            }
        }
        $impuesto = round($impuesto, 6);
        $baseGravada = round($baseGravada, 2);

        $cliente = 'ficha del cliente (umk.CLIENTE)';
        $fijo = 'fijo (igual en los pedidos de F12)';
        $conf = 'config/softland.php';

        $campos = [
            'PEDIDO' => [$numero, "consecutivo {$cfg['consecutivo']} (+1 al confirmar, con bloqueo)"],
            'ESTADO' => ['N', $fijo],
            'FECHA_PEDIDO' => [$hoy, 'fecha de hoy'],
            'FECHA_PROMETIDA' => [$hoy, 'fecha de hoy (= fecha del pedido)'],
            'FECHA_PROX_EMBARQU' => [$hoy, 'fecha de hoy'],
            'FECHA_ULT_EMBARQUE' => [$hoy, 'fecha de hoy'],
            'FECHA_ULT_CANCELAC' => ['1980-01-01 00:00:00.000', $fijo],
            'FECHA_ORDEN' => [$hoy, 'fecha de hoy'],
            'FECHA_PROYECTADA' => [$hoy, 'fecha de hoy'],
            'FECHA_HORA' => [$ahoraTxt, 'fecha y hora de creación'],
            'TARJETA_CREDITO' => [' ', $fijo],
            'CLIENTE' => [$c->CLIENTE, 'cliente elegido'],
            'CLIENTE_DIRECCION' => [$c->CLIENTE, 'mismo código de cliente'],
            'CLIENTE_CORPORAC' => [$c->CLIENTE, 'mismo código de cliente'],
            'CLIENTE_ORIGEN' => [$c->CLIENTE, 'mismo código de cliente'],
            'NOMBRE_CLIENTE' => [$c->NOMBRE, "{$cliente} NOMBRE"],
            'EMBARCAR_A' => [$c->ALIAS, "{$cliente} ALIAS"],
            'DIRECCION_FACTURA' => [$c->DIRECCION, "{$cliente} DIRECCION"],
            'DIREC_EMBARQUE' => [$c->DIR_EMB_DEFAULT, "{$cliente} DIR_EMB_DEFAULT"],
            'DIVISION_GEOGRAFICA1' => [$c->DIVISION_GEOGRAFICA1, $cliente],
            'DIVISION_GEOGRAFICA2' => [$c->DIVISION_GEOGRAFICA2, $cliente],
            'CONDICION_PAGO' => [$c->CONDICION_PAGO, $cliente],
            'NIVEL_PRECIO' => [$c->NIVEL_PRECIO, $cliente],
            'MONEDA' => [$moneda, "moneda del nivel ({$cliente} MONEDA_NIVEL)"],
            'MONEDA_PEDIDO' => [$moneda, "moneda del nivel ({$cliente} MONEDA_NIVEL)"],
            'PAIS' => [$cfg['pais'], $conf],
            'VENDEDOR' => [$cfg['vendedor'], $conf],
            'COBRADOR' => [$cfg['cobrador'], "{$conf} (= vendedor)"],
            'RUTA' => [$cfg['ruta'], $conf],
            'ZONA' => [$cfg['zona'], $conf],
            'BODEGA' => [$cfg['bodega'], $conf],
            'USUARIO' => [$cfg['usuario'], $conf],
            'OBSERVACIONES' => [filled($observaciones) ? trim($observaciones) : null, 'campo Observaciones del pedido (p. ej. la orden de compra)'],
            'ORDEN_COMPRA' => [null, 'no se usa en los pedidos de F12'],
            'TOTAL_MERCADERIA' => [$mercaderia, 'suma de precio × cantidad'],
            'TOTAL_IMPUESTO1' => [$impuesto, 'Σ subtotal × % IVA del artículo (umk.IMPUESTO; EXE = 0 %)'],
            'TOTAL_IMPUESTO2' => [0, $fijo],
            'TOTAL_A_FACTURAR' => [round($mercaderia + $impuesto, 6), 'mercadería + impuesto'],
            'TOTAL_UNIDADES' => [$unidades, 'suma de cantidades'],
            'BASE_IMPUESTO1' => [$baseGravada, 'Σ subtotal de las líneas con IVA'],
            'BASE_IMPUESTO2' => [0, $fijo],
            'MONTO_ANTICIPO' => [0, $fijo],
            'MONTO_FLETE' => [0, $fijo],
            'MONTO_SEGURO' => [0, $fijo],
            'MONTO_DOCUMENTACIO' => [0, $fijo],
            'MONTO_OTRO_CARGO' => [0, $fijo],
            'TIPO_DESCUENTO1' => ['P', $fijo],
            'TIPO_DESCUENTO2' => ['P', $fijo],
            'MONTO_DESCUENTO1' => [0, $fijo],
            'MONTO_DESCUENTO2' => [0, $fijo],
            'PORC_DESCUENTO1' => [0, $fijo],
            'PORC_DESCUENTO2' => [0, $fijo],
            'DESCUENTO_VOLUMEN' => [0, $fijo],
            'PORC_COMI_VENDEDOR' => [0, $fijo],
            'PORC_COMI_COBRADOR' => [0, $fijo],
            'PORC_INTCTE' => [0, $fijo],
            'TOTAL_CANCELADO' => [0, $fijo],
            'TOTAL_ASUMIDO_EMISOR' => [0, $fijo],
            'IMPRESO' => ['N', $fijo],
            'TIPO_PEDIDO' => ['N', $fijo],
            'CLASE_PEDIDO' => ['N', $fijo],
            'TIPO_DOCUMENTO' => ['P', $fijo],
            'DOC_A_GENERAR' => ['F', $fijo],
            'ORIGEN_PEDIDO' => ['F', $fijo],
            'TIPO_DOC_CXC' => ['FAC', $fijo],
            'SUBTIPO_DOC_CXC' => [0, $fijo],
            'VERSION_NP' => [1, $fijo],
            'AUTORIZADO' => ['N', $fijo],
            'BACKORDER' => ['N', $fijo],
            'DESCUENTO_CASCADA' => ['S', $fijo],
            'FIJAR_TIPO_CAMBIO' => ['N', $fijo],
            'TIPO_CAMBIO' => [null, $fijo],
            'ES_FACTURA_REEMPLAZO' => ['N', $fijo],
            'PED_MOD_POST_AUTORIZ' => ['N', $fijo],
            'NoteExistsFlag' => [0, $fijo],
            'RecordDate' => [$ahoraTxt, 'obligatorio · el trigger de auditoría lo repone con la hora del servidor'],
            'CreateDate' => [$ahoraTxt, 'obligatorio · el trigger de auditoría lo repone con la hora del servidor'],
            'CreatedBy' => [$usuario, 'obligatorio · usuario de SQL Server (el trigger de auditoría pone el mismo)'],
            'UpdatedBy' => [$usuario, 'obligatorio · usuario de SQL Server (el trigger de auditoría pone el mismo)'],
            'RowPointer' => ['NEWID()', 'lo genera SQL Server al insertar'],
        ];

        return array_map(fn ($v) => ['valor' => $v[0], 'origen' => $v[1]], $campos);
    }

    /**
     * Líneas de Softland.umk.PEDIDO_LINEA (modelo: pedido pendiente 00007807).
     *
     * @param  array<array-key, array{codigo:string,precio:float,cantidad:float,lote?:?string}>  $items
     * @return list<array<string, array{valor:mixed, origen:string}>>
     */
    public function lineas(string $numero, array $items, CarbonInterface $ahora): array
    {
        $cfg = $this->config;
        $hoy = $ahora->copy()->startOfDay()->format('Y-m-d H:i:s.v');
        $ahoraTxt = $ahora->format('Y-m-d H:i:s.v');
        $usuario = $this->usuarioAuditoria();
        $fijo = 'fijo (igual en los pedidos de F12)';
        $fiscal = $this->datosFiscales(array_map(fn ($i) => (string) $i['codigo'], $items));

        $lineas = [];
        foreach (array_values($items) as $n => $i) {
            $codigo = (string) $i['codigo'];
            $f = $fiscal[$codigo] ?? null;
            $porc = (float) ($f->PORC ?? 0);
            $impuestoArt = $f->IMPUESTO ?? '?';
            $cuentaArt = $f->ARTICULO_CUENTA ?? '?';

            $campos = [
                'PEDIDO' => [$numero, 'número del encabezado'],
                'PEDIDO_LINEA' => [$n + 1, 'consecutivo de línea (1, 2, 3…)'],
                'LINEA_USUARIO' => [$n, 'orden de línea (0, 1, 2…)'],
                'ARTICULO' => [$codigo, 'artículo del carrito'],
                'BODEGA' => [$cfg['bodega'], 'config/softland.php'],
                'LOTE' => [$i['lote'] ?? null, 'lote elegido (sugerido: el que vence primero)'],
                'LOCALIZACION' => [null, $fijo],
                'ESTADO' => ['N', $fijo],
                'PRECIO_UNITARIO' => [(float) $i['precio'], 'precio del nivel del cliente'],
                'CANTIDAD_PEDIDA' => [(float) $i['cantidad'], 'cantidad del carrito'],
                'CANTIDAD_A_FACTURA' => [(float) $i['cantidad'], '= cantidad pedida'],
                'CANTIDAD_FACTURADA' => [0, $fijo],
                'CANTIDAD_RESERVADA' => [0, $fijo],
                'CANTIDAD_BONIFICAD' => [0, $fijo],
                'CANTIDAD_CANCELADA' => [0, $fijo],
                'FECHA_ENTREGA' => [$hoy, 'fecha de hoy'],
                'FECHA_PROMETIDA' => [$hoy, 'fecha de hoy'],
                'CUENTA_CONTABLE' => [$f->CTA_VENTAS_LOC ?? null, "cuenta de artículo {$cuentaArt} → umk.ARTICULO_CUENTA.CTA_VENTAS_LOC"],
                'CENTRO_COSTO' => [$f->CTR_VENTAS_LOC ?? null, "cuenta de artículo {$cuentaArt} → umk.ARTICULO_CUENTA.CTR_VENTAS_LOC"],
                'PORC_IMPUESTO1' => [$porc, "impuesto del artículo {$impuestoArt} → umk.IMPUESTO.IMPUESTO1"],
                'PORC_IMP1_BASE' => [$porc, "= % de impuesto ({$impuestoArt})"],
                'PORC_IMPUESTO2' => [0, $fijo],
                'PORC_IMP2_BASE' => [0, $fijo],
                'TIPO_DESCUENTO' => ['P', $fijo],
                'MONTO_DESCUENTO' => [0, $fijo],
                'PORC_DESCUENTO' => [0, $fijo],
                'TIPO_DESC' => ['0', $fijo],
                'PORC_EXONERACION' => [0, $fijo],
                'MONTO_EXONERACION' => [0, $fijo],
                'PORC_EXONERACION2' => [0, $fijo],
                'MONTO_EXONERACION2' => [0, $fijo],
                'ES_OTRO_CARGO' => ['N', $fijo],
                'ES_CANASTA_BASICA' => ['N', $fijo],
                'DESCRIPCION' => ['', $fijo],
                'COMENTARIO' => ['', $fijo],
                'NoteExistsFlag' => [0, $fijo],
                'RecordDate' => [$ahoraTxt, 'obligatorio · el trigger de auditoría lo repone con la hora del servidor'],
                'CreateDate' => [$ahoraTxt, 'obligatorio · el trigger de auditoría lo repone con la hora del servidor'],
                'CreatedBy' => [$usuario, 'obligatorio · usuario de SQL Server (el trigger de auditoría pone el mismo)'],
                'UpdatedBy' => [$usuario, 'obligatorio · usuario de SQL Server (el trigger de auditoría pone el mismo)'],
                'RowPointer' => ['NEWID()', 'lo genera SQL Server al insertar'],
            ];

            $lineas[] = array_map(fn ($v) => ['valor' => $v[0], 'origen' => $v[1]], $campos);
        }

        return $lineas;
    }

    /**
     * Impuesto (código y %) y cuenta contable de venta de cada artículo.
     *
     * @param  list<string>  $codigos
     * @return array<string, object>
     */
    private function datosFiscales(array $codigos): array
    {
        $faltan = array_values(array_diff(array_unique($codigos), array_keys($this->fiscal)));
        if ($faltan !== []) {
            $e = $this->config['esquema'];
            $this->db->table($e.'.ARTICULO as a')
                ->leftJoin($e.'.IMPUESTO as i', 'i.IMPUESTO', '=', 'a.IMPUESTO')
                ->leftJoin($e.'.ARTICULO_CUENTA as ac', 'ac.ARTICULO_CUENTA', '=', 'a.ARTICULO_CUENTA')
                ->select(['a.ARTICULO', 'a.IMPUESTO', 'i.IMPUESTO1 as PORC', 'a.ARTICULO_CUENTA', 'ac.CTA_VENTAS_LOC', 'ac.CTR_VENTAS_LOC'])
                ->whereIn('a.ARTICULO', $faltan)
                ->get()
                ->each(fn ($r) => $this->fiscal[trim($r->ARTICULO)] = $r);
        }

        return array_intersect_key($this->fiscal, array_flip($codigos));
    }

    private ?string $usuarioAuditoria = null;

    /**
     * Valor de CreatedBy / UpdatedBy (columnas obligatorias).
     *
     * Los triggers de auditoría de Softland (EXPEDIDOIup, EXPEDIDO_LINEAIup,
     * EXCONSECUTIVO_FAIup) los reponen con ERPADMIN.LeerUsuarioActual, que para
     * esta app (sin sesión en ERPADMIN.USUARIO_ACTUAL) devuelve el login de SQL
     * Server. Se envía ese mismo valor para que lo que se ve sea lo que queda.
     */
    public function usuarioAuditoria(): string
    {
        if ($this->usuarioAuditoria === null) {
            $this->usuarioAuditoria = $this->db->getDriverName() === 'sqlsrv'
                ? (string) $this->db->selectOne('SELECT SUSER_SNAME() AS u')->u
                : 'FA/'.$this->config['usuario']; // pruebas sin SQL Server
        }

        return $this->usuarioAuditoria;
    }

    /** Ficha actual del cliente (Softland la copia al crear el pedido). */
    private function fichaCliente(string $codigo): object
    {
        $c = $this->db->table($this->config['esquema'].'.CLIENTE')
            ->select(['CLIENTE', 'NOMBRE', 'ALIAS', 'DIRECCION', 'DIR_EMB_DEFAULT', 'DIVISION_GEOGRAFICA1',
                'DIVISION_GEOGRAFICA2', 'CONDICION_PAGO', 'NIVEL_PRECIO', 'MONEDA_NIVEL'])
            ->where('CLIENTE', trim($codigo))
            ->first();

        if (! $c) {
            throw new RuntimeException("El cliente {$codigo} no existe en Softland.");
        }

        return $c;
    }
}
