<?php

namespace App\Cotizaciones;

/**
 * Concentración de un medicamento a partir de su descripción, para la columna
 * "Concentración" de la cotización (el catálogo no la tiene aparte).
 *
 *   "ANASTROZOL 1 MG TAB RECUBIERTA 28/CAJA"           → "1 mg"
 *   "Ceftriaxona 1Gr Polvo para Sol. iny."              → "1 GR"
 *   "BECLOMETASONA 250 MCG/DOSIS SUSPENSION…"           → "250 mcg/dosis"
 *   "CIPROFLOXACINA 200 MG/100 ML SOLUCION…"            → "200 mg/100 ml"
 *   "Bencilpenicilina Sodica 1,000,000 ui Polvo…"       → "1,000,000 UI"
 *   "CALNOR (Amlodipina + Olmesartan) 10mgx40mg…"       → "10 mg x 40 mg"
 *   "TERABAC 131l x 200 mCi Calibrado"                  → "200 mCi"
 *   "GLUCOSA EN AGUA 50% SOLUCIÓN…"                     → "50%"
 *
 * No cuenta como concentración el volumen o la cantidad del envase
 * ("Jarabe 120 ml/Frasco", "Kit 5 unidades"): ahí devuelve '' (celda vacía).
 */
final class Concentracion
{
    // Número con miles ("1,000,000") o decimal ("0.5", "2,5").
    private const NUM = '\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:[.,]\d+)?';

    // Unidades (las largas primero). "GR", "GRS", "GMS" = gramos.
    private const UNIDAD = 'MCG|MG|KG|GRS|GR|GMS|G|ML|L|U\.I\.|UI|MEQ|MCI|U';

    /** Unidades que solas son volumen del envase, no concentración. */
    private const SOLO_VOLUMEN = ['ML', 'L'];

    public static function de(string $descripcion): string
    {
        $n = self::NUM;
        $u = self::UNIDAD;
        // número + unidad [/ [número] unidad|DOSIS] [x número unidad [/ [número] unidad]]   o   número %
        $patron = "/(?<![\w.,])(?<n1>{$n})\s*(?<u1>{$u})(?![A-WYZ])"
            ."(?:\s*\/\s*(?<n2>{$n})?\s*(?<u2>{$u}|DOSIS)(?![A-Z]))?"
            ."(?:\s*X\s*(?<n3>{$n})\s*(?<u3>{$u})(?![A-Z])"
            ."(?:\s*\/\s*(?<n4>{$n})?\s*(?<u4>{$u}|DOSIS)(?![A-Z]))?)?"
            ."|(?<![\w.,])(?<pct>{$n})\s*%/u";

        $texto = mb_strtoupper($descripcion);
        $desde = 0;
        while (preg_match($patron, $texto, $m, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $desde)) {
            $v = fn (string $k) => $m[$k][0] ?? null;

            if ($v('pct') !== null) {
                return $v('pct').'%';
            }

            $finUnidad = $m['u1'][1] + strlen($v('u1'));
            $sigue = substr($texto, $finUnidad, 1);
            $volumen = in_array($v('u1'), self::SOLO_VOLUMEN, true) && $v('u2') === null;
            $calibre = $v('u1') === 'G' && $sigue === 'X' && $v('u3') === null; // aguja "21Gx1.5"
            if ($volumen || $calibre) {
                // No es concentración (volumen del envase / calibre): se sigue buscando justo después.
                $desde = $finUnidad;

                continue;
            }

            $res = $v('n1').' '.self::unidad($v('u1'));
            if ($v('u2') !== null) {
                $res .= '/'.($v('n2') !== null ? $v('n2').' ' : '').self::unidad($v('u2'));
            }
            if ($v('u3') !== null) {
                $res .= ' x '.$v('n3').' '.self::unidad($v('u3'));
                if ($v('u4') !== null) {
                    $res .= '/'.($v('n4') !== null ? $v('n4').' ' : '').self::unidad($v('u4'));
                }
            }

            return $res;
        }

        return '';
    }

    private static function unidad(string $u): string
    {
        return match ($u) {
            'MCG' => 'mcg',
            'MG' => 'mg',
            'GR', 'GRS' => 'GR', // escrito "Gr" en la descripción: se respeta como GR
            'G', 'GMS' => 'g',
            'KG' => 'kg',
            'ML' => 'ml',
            'L' => 'l',
            'UI', 'U.I.' => 'UI',
            'MEQ' => 'mEq',
            'MCI' => 'mCi',
            'U' => 'U',
            'DOSIS' => 'dosis',
            default => mb_strtolower($u),
        };
    }
}
