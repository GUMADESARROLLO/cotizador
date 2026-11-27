<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento adjunto a un pedido ingresado en Softland (archivo en el bucket, Cotizaciones/). */
#[Fillable(['nombre', 'ruta', 'tipo', 'tamano'])]
class PedidoAdjunto extends Model
{
    protected function casts(): array
    {
        return ['tamano' => 'integer'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /** Fila para la tabla del modal. */
    public function toFila(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'extension' => strtoupper(pathinfo($this->nombre, PATHINFO_EXTENSION)),
            'tamano' => $this->tamano,
            'fecha' => $this->created_at?->format('d/m/Y H:i'),
            'fecha_orden' => $this->created_at?->format('Y-m-d H:i:s'),
            'descargar' => route('pedidos.adjuntos.descargar', [$this->pedido_id, $this->id]),
            'eliminar' => route('pedidos.adjuntos.eliminar', [$this->pedido_id, $this->id]),
        ];
    }
}
