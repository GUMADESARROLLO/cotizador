<?php

if (! function_exists('dinero')) {
    /**
     * @param  string|null  $moneda  'L' (C$) / 'D' (US$); null = moneda de precios
     *                               de la petición actual (la del nivel del cliente).
     */
    function dinero(float|int|string|null $valor, ?string $moneda = null): string
    {
        $simbolo = $moneda
            ? config("softland.monedas.{$moneda}", config('articulos.currency', '$'))
            : config('articulos.currency', '$');

        return $simbolo.number_format((float) $valor, 2, '.', ',');
    }
}

if (! function_exists('cantidad')) {
    function cantidad(float|int|string|null $valor): string
    {
        $v = (float) $valor;

        return number_format($v, floor($v) == $v ? 0 : 2, '.', ',');
    }
}
