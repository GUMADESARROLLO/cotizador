<?php

namespace App\Pedidos;

use App\Catalogo\Articulo;
use Illuminate\Contracts\Session\Session;

/**
 * Pedido en armado, guardado en sesión.
 * La cantidad siempre se limita a la existencia actual del artículo.
 */
class Carrito
{
    private const KEY = 'carrito.items';

    public function __construct(private Session $session) {}

    /** @return array<string, array{codigo:string,descripcion:string,unidad:string,precio:float,existencia:float,cantidad:float}> */
    public function items(): array
    {
        return $this->session->get(self::KEY, []);
    }

    /**
     * Fija la cantidad de un artículo (0 lo quita). Devuelve la cantidad final
     * aplicada, que puede ser menor a la pedida si excede la existencia.
     */
    public function fijar(Articulo $articulo, float $cantidad): float
    {
        $items = $this->items();
        $cantidad = max(0, min(floor($cantidad), $articulo->maximoPedible()));

        if ($cantidad <= 0) {
            unset($items[$articulo->codigo]);
        } else {
            $items[$articulo->codigo] = [
                'codigo' => $articulo->codigo,
                'descripcion' => $articulo->descripcion,
                'unidad' => $articulo->unidad,
                'precio' => $articulo->precio,
                'existencia' => $articulo->existencia,
                'cantidad' => $cantidad,
            ];
        }

        $this->session->put(self::KEY, $items);

        return $cantidad;
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
        return round(array_sum(array_map(
            fn ($i) => $i['precio'] * $i['cantidad'],
            $this->items()
        )), 2);
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
