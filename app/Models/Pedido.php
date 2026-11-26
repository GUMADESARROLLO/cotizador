<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['folio', 'cliente_codigo', 'cliente', 'cliente_ruc', 'nivel_precio', 'moneda', 'condicion_pago', 'contacto', 'notas', 'lineas', 'piezas', 'total', 'credito_disponible',
    'softland_estado', 'softland_pedido', 'softland_enviado_at', 'softland_error'])]
class Pedido extends Model
{
    public const SOFTLAND_ENVIANDO = 'enviando';

    public const SOFTLAND_ENVIADO = 'enviado';

    public const SOFTLAND_ERROR = 'error';

    protected function casts(): array
    {
        return [
            'piezas' => 'float',
            'total' => 'float',
            'credito_disponible' => 'float',
            'softland_enviado_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function enviadoASoftland(): bool
    {
        return $this->softland_estado === self::SOFTLAND_ENVIADO;
    }

    /** Se puede enviar si nunca se envió o si el último intento falló. */
    public function puedeEnviarseASoftland(): bool
    {
        return in_array($this->softland_estado, [null, self::SOFTLAND_ERROR], true);
    }
}
