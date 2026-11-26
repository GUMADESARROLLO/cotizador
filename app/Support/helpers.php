<?php

if (! function_exists('dinero')) {
    function dinero(float|int|string|null $valor): string
    {
        return config('articulos.currency', '$').number_format((float) $valor, 2, '.', ',');
    }
}

if (! function_exists('cantidad')) {
    function cantidad(float|int|string|null $valor): string
    {
        $v = (float) $valor;

        return number_format($v, floor($v) == $v ? 0 : 2, '.', ',');
    }
}
