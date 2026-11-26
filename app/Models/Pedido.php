<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['folio', 'cliente_codigo', 'cliente', 'cliente_ruc', 'contacto', 'notas', 'lineas', 'piezas', 'total', 'credito_disponible'])]
class Pedido extends Model
{
    protected function casts(): array
    {
        return [
            'piezas' => 'float',
            'total' => 'float',
            'credito_disponible' => 'float',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }
}
