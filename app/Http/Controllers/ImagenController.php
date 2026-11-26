<?php

namespace App\Http\Controllers;

use App\Catalogo\Imagenes;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImagenController extends Controller
{
    /**
     * Genera (solo la primera vez) y entrega la miniatura de un producto.
     * Las búsquedas siguientes ya apuntan al archivo estático en /thumbs.
     */
    public function miniatura(string $codigo, Imagenes $imagenes)
    {
        try {
            $ruta = $imagenes->miniatura($codigo);
        } catch (Throwable $e) {
            Log::warning("Miniatura {$codigo}: ".$e->getMessage());
            $ruta = null;
        }

        abort_unless($ruta, 404);

        return response()->file($ruta, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
