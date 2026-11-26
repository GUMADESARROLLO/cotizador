<?php

namespace App\Pedidos;

/**
 * Textos de una línea del pedido (carrito o pedido guardado), iguales en la
 * vista y en las respuestas JSON.
 *
 * Ej. con factor 50: "2,800 VIA = 56 CJA" y "C$32.93 / VIA · C$1,646.50 / CJA".
 */
final class Linea
{
    public static function tieneEmpaque(array $i): bool
    {
        return (float) ($i['factor'] ?? 1) > 1;
    }

    public static function unidadInterna(array $i): string
    {
        return ($i['unidad_empaque'] ?? '') ?: 'UND';
    }

    public static function unidadCaja(array $i): string
    {
        return ($i['unidad'] ?? '') ?: 'CJA';
    }

    /** Unidad en que se captura la cantidad (VIA o CJA). */
    public static function unidadModo(array $i): string
    {
        return self::enUnidades($i) ? self::unidadInterna($i) : self::unidadCaja($i);
    }

    public static function enUnidades(array $i): bool
    {
        return self::tieneEmpaque($i) && ($i['modo'] ?? Carrito::CAJA) === Carrito::UNIDAD;
    }

    /** "2,800 VIA = 56 CJA" (o al revés si se captura en cajas). null sin empaque. */
    public static function conversion(array $i): ?string
    {
        if (! self::tieneEmpaque($i)) {
            return null;
        }

        $unidades = cantidad($i['unidades'] ?? 0).' '.self::unidadInterna($i);
        $cajas = cantidad($i['cantidad']).' '.self::unidadCaja($i);

        return self::enUnidades($i) ? "{$unidades} = {$cajas}" : "{$cajas} = {$unidades}";
    }

    /** Lo capturado: "2,800 VIA" (o "56 CJA"). */
    public static function capturadaTexto(array $i): string
    {
        return cantidad(Carrito::capturada($i)).' '.self::unidadModo($i);
    }

    /** Su equivalente: "= 56 CJA" (o "= 2,800 VIA"). null sin empaque. */
    public static function equivalencia(array $i): ?string
    {
        if (! self::tieneEmpaque($i)) {
            return null;
        }

        return self::enUnidades($i)
            ? '= '.cantidad($i['cantidad']).' '.self::unidadCaja($i)
            : '= '.cantidad($i['unidades'] ?? 0).' '.self::unidadInterna($i);
    }

    /** Precio por unidad interna (precio por caja ÷ factor). */
    public static function precioUnidad(array $i): float
    {
        return round((float) $i['precio'] / max(1, (float) ($i['factor'] ?? 1)), 4);
    }

    /** "C$32.93 / VIA · C$1,646.50 / CJA", o solo por caja sin empaque. */
    public static function precioTexto(array $i): string
    {
        $caja = dinero($i['precio']).' / '.self::unidadCaja($i);

        return self::tieneEmpaque($i)
            ? dinero(self::precioUnidad($i)).' / '.self::unidadInterna($i).' · '.$caja
            : $caja;
    }

    public static function sinPrecio(array $i): bool
    {
        return (float) $i['precio'] <= 0;
    }
}
