<?php

namespace App\Cotizaciones;

use App\Clientes\Cliente;
use App\Models\ClienteFicha;
use App\Models\Pedido;

/**
 * Datos del cliente que van en la cotización (Cliente, Atención, Cargo, Teléfono y Fax).
 * La línea verde (responsable) NO va aquí: cambia en cada cotización y se guarda en el pedido.
 * Prioridad: lo guardado para ese cliente (lo escribió el vendedor) y, si
 * no hay, lo del pedido y lo de Softland. Al descargar se guarda para la próxima vez.
 */
final class FichaCotizacion
{
    /** @return array{nombre:string, atencion:string, cargo:string, telefono:string, fax:string} */
    public static function prellenar(Pedido $pedido, ?Cliente $cliente): array
    {
        $guardada = $pedido->cliente_codigo
            ? ClienteFicha::where('cliente_codigo', $pedido->cliente_codigo)->first()
            : null;

        $softland = [
            'nombre' => (string) $pedido->cliente,
            'atencion' => trim((string) $pedido->contacto) ?: (string) $cliente?->contacto,
            'cargo' => (string) $cliente?->cargo,
            'telefono' => trim((string) $cliente?->telefono1) ?: trim((string) $cliente?->telefono2),
            'fax' => (string) $cliente?->fax,
        ];

        $ficha = [];
        foreach (ClienteFicha::CAMPOS as $campo) {
            $valor = $guardada?->{$campo};
            $ficha[$campo] = trim($valor !== null && trim((string) $valor) !== '' ? (string) $valor : $softland[$campo]);
        }

        return $ficha;
    }

    /**
     * Responsables (línea verde) usados antes con este cliente, el más reciente primero:
     * sugerencias para no escribirlos otra vez.
     *
     * @return list<string>
     */
    public static function responsables(Pedido $pedido): array
    {
        if (! $pedido->cliente_codigo) {
            return [];
        }

        return Pedido::query()
            ->where('cliente_codigo', $pedido->cliente_codigo)
            ->whereNotNull('responsable')->where('responsable', '<>', '')
            ->latest('updated_at')->latest('id') // desempate: mismo segundo
            ->limit(50)
            ->pluck('responsable')
            ->map(fn ($r) => trim($r))
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    /** Guarda (o actualiza) la ficha del cliente con lo que se usó en la cotización. */
    public static function recordar(?string $clienteCodigo, array $ficha): void
    {
        if (! $clienteCodigo) {
            return;
        }

        ClienteFicha::updateOrCreate(
            ['cliente_codigo' => $clienteCodigo],
            array_map(fn ($v) => trim((string) $v) ?: null, array_intersect_key($ficha, array_flip(ClienteFicha::CAMPOS))),
        );
    }
}
