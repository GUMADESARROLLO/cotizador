<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoAdjunto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Documentos adjuntos de un pedido ya ingresado en Softland. Los archivos van al bucket,
 * en Cotizaciones/<N.º de pedido Softland>/; la tabla pedido_adjuntos guarda el registro.
 */
class AdjuntoController extends Controller
{
    public function index(Pedido $pedido): JsonResponse
    {
        $this->soloIngresado($pedido);

        return response()->json(['adjuntos' => $this->filas($pedido)]);
    }

    public function store(Request $request, Pedido $pedido): JsonResponse
    {
        $this->soloIngresado($pedido);
        $cfg = config('cotizacion.adjuntos');

        $request->validate([
            'archivos' => ['required', 'array', 'max:20'],
            'archivos.*' => ['file', 'max:'.$cfg['max_kb'], 'extensions:'.implode(',', $cfg['extensiones'])],
        ], [
            'archivos.required' => 'Elige al menos un archivo.',
            'archivos.*.max' => 'Un archivo pasa de '.round($cfg['max_kb'] / 1024).' MB.',
            'archivos.*.extensions' => 'Tipo de archivo no permitido.',
        ]);

        $disco = Storage::disk($cfg['disk']);
        $carpeta = trim($cfg['carpeta'], '/').'/'.($pedido->softland_pedido ?: 'pedido-'.$pedido->id);
        $subidos = 0;

        foreach ($request->file('archivos') as $archivo) {
            $original = $archivo->getClientOriginalName();
            $ext = strtolower($archivo->getClientOriginalExtension());
            // Nombre único y legible en el bucket: <base>_<aleatorio>.<ext> (sin espacios ni acentos).
            $base = Str::limit(Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'archivo', 80, '');
            $nombre = $base.'_'.Str::lower(Str::random(6)).($ext !== '' ? '.'.$ext : '');

            try {
                $ruta = $disco->putFileAs($carpeta, $archivo, $nombre);
            } catch (Throwable $e) {
                $ruta = false;
                Log::error("Adjunto del pedido {$pedido->id}: ".$e->getMessage());
            }
            if (! $ruta) {
                return response()->json([
                    'message' => "No se pudo subir \"{$original}\" al bucket. Intenta de nuevo.",
                    'adjuntos' => $this->filas($pedido),
                ], 502);
            }

            $pedido->adjuntos()->create([
                'nombre' => $original,
                'ruta' => $ruta,
                'tipo' => $archivo->getClientMimeType(),
                'tamano' => $archivo->getSize(),
            ]);
            $subidos++;
        }

        return response()->json([
            'message' => $subidos === 1 ? 'Archivo adjuntado.' : "{$subidos} archivos adjuntados.",
            'adjuntos' => $this->filas($pedido),
        ]);
    }

    public function descargar(Pedido $pedido, PedidoAdjunto $adjunto)
    {
        abort_unless($adjunto->pedido_id === $pedido->id, 404);
        $disco = Storage::disk(config('cotizacion.adjuntos.disk'));
        abort_unless($disco->exists($adjunto->ruta), 404, 'El archivo ya no está en el bucket.');

        return $disco->download($adjunto->ruta, $adjunto->nombre);
    }

    public function destroy(Pedido $pedido, PedidoAdjunto $adjunto): JsonResponse
    {
        abort_unless($adjunto->pedido_id === $pedido->id, 404);

        try {
            Storage::disk(config('cotizacion.adjuntos.disk'))->delete($adjunto->ruta);
        } catch (Throwable $e) {
            Log::warning("Borrar adjunto {$adjunto->id}: ".$e->getMessage());
        }
        $adjunto->delete();

        return response()->json(['message' => 'Adjunto eliminado.', 'adjuntos' => $this->filas($pedido)]);
    }

    private function filas(Pedido $pedido): array
    {
        return $pedido->adjuntos()->get()->map->toFila()->all();
    }

    /** Solo los pedidos ya ingresados en Softland llevan adjuntos. */
    private function soloIngresado(Pedido $pedido): void
    {
        abort_unless($pedido->enviadoASoftland(), 409, 'Solo se adjuntan documentos a pedidos ingresados en Softland.');
    }
}
