<?php

namespace App\Catalogo;

/**
 * Conversión entre cajas (unidad de venta en Softland) y unidades internas
 * (viales, tabletas…), con FACTOR_EMPAQUE unidades por caja.
 *
 * Ej.: 2,800 viales ÷ 50 = 56 cajas; 2,810 viales = 56.2 cajas (se permiten fracciones).
 */
final class Cantidades
{
    /** Redondeo hacia abajo a 4 decimales (evita prometer más de lo disponible). */
    public static function bajar4(float $valor): float
    {
        return floor(round($valor * 10000, 6)) / 10000;
    }

    /** Unidades completas que caben en $cajas (tolerancia a errores de coma flotante). */
    public static function unidades(float $cajas, float $factor): float
    {
        return floor(round($cajas * $factor, 6));
    }

    /** Cajas para $unidades, con la precisión de Softland (8 decimales). */
    public static function cajas(float $unidades, float $factor): float
    {
        return round($unidades / max(1, $factor), 8);
    }
}
