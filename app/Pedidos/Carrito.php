<?php

namespace App\Pedidos;

use App\Catalogo\Articulo;
use App\Catalogo\Cantidades;
use App\Catalogo\Lote;
use App\Clientes\Cliente;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Session\Session;

/**
 * Pedido en armado, guardado en sesión.
 * La cantidad siempre se limita a la existencia actual del artículo (o del lote).
 */
class Carrito
{
    private const KEY = 'carrito.items';

    private const CLIENTE = 'carrito.cliente';

    private const EDITANDO = 'carrito.editando';

    private const OBSERVACIONES = 'carrito.observaciones';

    private const FECHA = 'carrito.fecha';

    /** Modos de captura de la cantidad. */
    public const UNIDAD = 'unidad';

    public const CAJA = 'caja';

    public function __construct(private Session $session) {}

    /** Cliente elegido para este pedido (copia tomada de SQL Server al seleccionarlo). */
    public function cliente(): ?Cliente
    {
        $data = $this->session->get(self::CLIENTE);

        return $data ? Cliente::fromArray($data) : null;
    }

    public function fijarCliente(Cliente $cliente): void
    {
        $this->session->put(self::CLIENTE, $cliente->toArray());
    }

    /** Pedido terminado: quita artículos, cliente y edición para empezar otro. */
    public function terminar(): void
    {
        $this->session->forget([self::KEY, self::CLIENTE, self::EDITANDO, self::OBSERVACIONES, self::FECHA]);
    }

    /** Fecha del pedido (por defecto hoy). Se elige en el calendario de la pantalla Pedido. */
    public function fecha(): CarbonImmutable
    {
        $guardada = $this->session->get(self::FECHA);

        return $guardada ? CarbonImmutable::parse($guardada)->startOfDay() : CarbonImmutable::today();
    }

    public function fijarFecha(CarbonInterface $fecha): void
    {
        $this->session->put(self::FECHA, $fecha->format('Y-m-d'));
    }

    /** Id del pedido de MySQL que se está modificando (null = pedido nuevo). */
    public function editando(): ?int
    {
        return $this->session->get(self::EDITANDO);
    }

    public function editar(int $pedidoId, ?string $observaciones, ?CarbonInterface $fecha = null): void
    {
        $this->session->put(self::EDITANDO, $pedidoId);
        $this->session->put(self::OBSERVACIONES, $observaciones);
        if ($fecha) {
            $this->fijarFecha($fecha);
        }
    }

    public function observaciones(): ?string
    {
        return $this->session->get(self::OBSERVACIONES);
    }

    /**
     * Cada artículo guarda la cantidad en CAJAS (unidad de venta de Softland,
     * con fracción) y, si se capturó en unidades internas, también esas unidades.
     *
     * @return array<string, array{codigo:string,descripcion:string,unidad:string,unidad_empaque:string,factor:float,modo:string,unidades:float,cantidad:float,precio:float,precio_nivel:float,precio_manual:bool,existencia:float,lote:?string,lote_vence:?string,lote_disponible:?float}>
     */
    public function items(): array
    {
        return $this->session->get(self::KEY, []);
    }

    /** Cantidad tal como la capturó el vendedor: unidades (viales…) o cajas, según su modo. */
    public static function capturada(array $item): float
    {
        return ($item['modo'] ?? self::CAJA) === self::UNIDAD ? (float) ($item['unidades'] ?? 0) : (float) $item['cantidad'];
    }

    /**
     * Fija la cantidad de un artículo (0 lo quita). $cantidad va en la unidad del
     * modo: 'unidad' = unidades internas (2,800 viales → 56 cajas con factor 50),
     * 'caja' = cajas (se permiten fracciones). Sin modo se conserva el que tenía;
     * por defecto, cajas (las unidades se muestran como referencia).
     *
     * Devuelve la cantidad aplicada en esa misma unidad; puede ser menor a la
     * pedida si excede lo disponible: con lote, la existencia de ESE lote (un
     * lote por artículo); sin lote, la de la bodega.
     *
     * Un precio puesto a mano (fijarPrecio) se conserva al cambiar cantidad o lote.
     */
    public function fijar(Articulo $articulo, float $cantidad, ?Lote $lote = null, ?string $modo = null): float
    {
        $items = $this->items();
        $previo = $items[$articulo->codigo] ?? null;
        $factor = $articulo->factor;
        $modo = $articulo->tieneEmpaque()
            ? ($modo ?? $previo['modo'] ?? self::CAJA)
            : self::CAJA;

        if ($modo === self::UNIDAD) {
            $maximo = $lote ? $lote->maximoUnidades($factor) : $articulo->maximoUnidades();
            $unidades = max(0, min(floor($cantidad), $maximo));
            $cajas = Cantidades::cajas($unidades, $factor);
            $aplicada = $unidades;
        } else {
            $maximo = $lote ? $lote->maximoPedible() : $articulo->maximoPedible();
            $cajas = max(0, min(Cantidades::bajar4($cantidad), $maximo));
            $unidades = round($cajas * $factor, 4);
            $aplicada = $cajas;
        }

        if ($cajas <= 0) {
            unset($items[$articulo->codigo]);
        } else {
            $manual = (bool) ($previo['precio_manual'] ?? false);
            $items[$articulo->codigo] = [
                'codigo' => $articulo->codigo,
                'descripcion' => $articulo->descripcion,
                'unidad' => $articulo->unidad,
                'unidad_empaque' => $articulo->unidadEmpaque,
                'factor' => $factor,
                'modo' => $modo,
                'unidades' => $unidades,
                'cantidad' => $cajas,
                // Precio por caja (lo que va a Softland). El del nivel queda como referencia.
                'precio' => $manual ? (float) $previo['precio'] : $articulo->precio,
                'precio_nivel' => $articulo->precio,
                'precio_manual' => $manual,
                'existencia' => $articulo->existencia,
                'lote' => $lote?->lote,
                'lote_vence' => $lote?->vence,
                'lote_disponible' => $lote?->disponible,
            ];
        }

        $this->session->put(self::KEY, $items);

        return $aplicada;
    }

    /**
     * Precio puesto a mano para un artículo del pedido. $por = 'unidad' lo
     * multiplica por el factor (C$32.93 por vial × 50 = C$1,646.50 por caja);
     * 'caja' lo toma tal cual. null vuelve al precio del nivel del cliente.
     */
    public function fijarPrecio(string $codigo, ?float $precio, string $por = self::CAJA): ?array
    {
        $items = $this->items();
        if (! isset($items[$codigo])) {
            return null;
        }

        $item = &$items[$codigo];
        if ($precio === null) {
            $item['precio'] = (float) $item['precio_nivel'];
            $item['precio_manual'] = false;
        } else {
            $porCaja = $por === self::UNIDAD ? $precio * (float) $item['factor'] : $precio;
            $item['precio'] = round($porCaja, 4);
            $item['precio_manual'] = true;
        }
        unset($item);

        $this->session->put(self::KEY, $items);

        return $items[$codigo];
    }

    /** Subtotal de una línea: precio por caja × cajas. */
    public static function subtotal(array $item): float
    {
        return round((float) $item['precio'] * (float) $item['cantidad'], 2);
    }

    /**
     * Lote que corresponde al artículo: el ya elegido si sigue disponible;
     * si no, el sugerido (el que vence primero). null si no hay lotes.
     *
     * @param  list<Lote>  $lotes  ordenados por vencimiento
     */
    public function loteVigente(string $codigo, array $lotes): ?Lote
    {
        $elegido = $this->items()[$codigo]['lote'] ?? null;

        foreach ($lotes as $l) {
            if ($l->lote === $elegido) {
                return $l;
            }
        }

        return $lotes[0] ?? null;
    }

    public function quitar(string $codigo): void
    {
        $items = $this->items();
        unset($items[$codigo]);
        $this->session->put(self::KEY, $items);
    }

    public function vaciar(): void
    {
        $this->session->forget(self::KEY);
    }

    public function cantidadDe(string $codigo): float
    {
        return $this->items()[$codigo]['cantidad'] ?? 0;
    }

    public function totalPiezas(): float
    {
        return array_sum(array_column($this->items(), 'cantidad'));
    }

    public function total(): float
    {
        return round(array_sum(array_map(self::subtotal(...), $this->items())), 2);
    }

    public function vacio(): bool
    {
        return $this->items() === [];
    }

    public function resumen(): array
    {
        return [
            'lineas' => count($this->items()),
            'piezas' => $this->totalPiezas(),
            'total' => $this->total(),
        ];
    }
}
