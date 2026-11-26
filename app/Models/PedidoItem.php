<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['codigo', 'descripcion', 'unidad', 'lote', 'lote_vence', 'precio', 'cantidad', 'existencia', 'subtotal'])]
class PedidoItem extends Model
{
    protected function casts(): array
    {
        return [
            'precio' => 'float',
            'cantidad' => 'float',
            'existencia' => 'float',
            'subtotal' => 'float',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
