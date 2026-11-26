<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['codigo', 'descripcion', 'unidad', 'lote', 'lote_vence', 'precio', 'precio_nivel', 'precio_manual', 'cantidad', 'modo', 'unidades', 'factor', 'unidad_empaque', 'existencia', 'subtotal'])]
class PedidoItem extends Model
{
    protected function casts(): array
    {
        return [
            'precio' => 'float',
            'precio_nivel' => 'float',
            'precio_manual' => 'boolean',
            'cantidad' => 'float',
            'unidades' => 'float',
            'factor' => 'float',
            'existencia' => 'float',
            'subtotal' => 'float',
        ];
    }

    /** Como lo guarda el carrito (para Linea y para volver a cargarlo al modificar). */
    public function comoLinea(): array
    {
        return [
            'codigo' => (string) $this->codigo,
            'unidad' => (string) $this->unidad,
            'unidad_empaque' => (string) $this->unidad_empaque,
            'factor' => $this->factor ?: 1.0,
            'modo' => $this->modo ?: 'caja',
            'unidades' => $this->unidades ?? $this->cantidad,
            'cantidad' => $this->cantidad,
            'precio' => $this->precio,
            'precio_nivel' => $this->precio_nivel ?? $this->precio,
            'precio_manual' => (bool) $this->precio_manual,
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
