<?php

use Illuminate\Support\Carbon;

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

if (! function_exists('fecha_larga')) {
    /** "lunes 30 de noviembre de 2026" */
    function fecha_larga(DateTimeInterface|string|null $fecha): string
    {
        return $fecha ? Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') : '';
    }
}

if (! function_exists('fecha_corta')) {
    /** "Lun 30 nov 2026" */
    function fecha_corta(DateTimeInterface|string|null $fecha): string
    {
        if (! $fecha) {
            return '';
        }
        $f = Carbon::parse($fecha)->locale('es');

        return ucfirst(rtrim($f->isoFormat('ddd'), '.')).' '.$f->day.' '.rtrim($f->isoFormat('MMM'), '.').' '.$f->year;
    }
}

if (! function_exists('cantidad')) {
    function cantidad(float|int|string|null $valor): string
    {
        $v = (float) $valor;

        // Hasta 4 decimales, sin ceros de más: 56 · 56.2 · 0.0357
        return rtrim(rtrim(number_format($v, 4, '.', ','), '0'), '.');
    }
}
