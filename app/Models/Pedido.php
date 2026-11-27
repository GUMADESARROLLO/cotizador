<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['folio', 'fecha_pedido', 'cliente_codigo', 'cliente', 'cliente_ruc', 'nivel_precio', 'moneda', 'condicion_pago', 'contacto', 'licitacion', 'notas', 'lineas', 'piezas', 'total', 'credito_disponible',
    'softland_estado', 'softland_pedido', 'softland_consecutivo', 'softland_enviado_at', 'softland_error'])]
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
            'fecha_pedido' => 'date',
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

    /** Consecutivo de Softland del pedido (el elegido o, si no, el de la configuración). */
    public function consecutivo(): string
    {
        return $this->softland_consecutivo ?: config('softland.consecutivo');
    }

    /**
     * Número que ve la gente: el de Softland ("N.º 00007837"). Mientras no se
     * envía, "Sin enviar" (el folio P-… es solo interno, para no confundir).
     */
    public function numero(): string
    {
        return $this->softland_pedido ? 'N.º '.$this->softland_pedido : 'Sin enviar';
    }
}
