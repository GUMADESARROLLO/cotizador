<?php

namespace App\Http\Controllers;

use App\Catalogo\Articulo;
use App\Catalogo\Cantidades;
use App\Catalogo\Catalogo;
use App\Catalogo\Imagenes;
use App\Catalogo\Lote;
use App\Catalogo\Lotes;
use App\Models\Pedido;
use App\Pedidos\Carrito;
use App\Pedidos\Linea;
use App\Softland\ConsecutivoPedido;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CarritoController extends Controller
{
    public function index(Carrito $carrito, ConsecutivoPedido $consecutivo, Lotes $lotes, Imagenes $imagenes)
    {
        // Consecutivo elegido (por defecto DESP-A), los DESP- que se pueden elegir
        // y el número tentativo que tendría el pedido en Softland (solo lectura).
        $codigo = $carrito->consecutivo() ?? $consecutivo->codigo();
        $consecutivos = [];
        $numero = null;
        try {
            if (! $carrito->vacio()) {
                $consecutivos = $consecutivo->disponibles();
                $numero = $consecutivos[$codigo]['siguiente'] ?? $consecutivo->siguiente($codigo);
            }
        } catch (Throwable $e) {
            Log::warning('Consecutivo de pedido Softland: '.$e->getMessage());
        }

        // Lotes de la bodega para elegir en cada artículo.
        try {
            $lotesPorArticulo = $carrito->vacio() ? [] : $lotes->porArticulos(array_keys($carrito->items()));
        } catch (Throwable $e) {
            Log::warning('Lotes Softland: '.$e->getMessage());
            $lotesPorArticulo = [];
        }

        return view('carrito.index', [
            'items' => $carrito->items(),
            'lotes' => $lotesPorArticulo,
            'imagenes' => $imagenes->urls(array_keys($carrito->items())),
            'resumen' => $carrito->resumen(),
            'cliente' => $carrito->cliente(),
            'numeroPedido' => $numero,
            'codigoConsecutivo' => $codigo,
            'consecutivos' => $consecutivos,
            // Pedido guardado que se está modificando (null = pedido nuevo).
            'editando' => $carrito->editando() ? Pedido::find($carrito->editando()) : null,
            'observaciones' => $carrito->observaciones(),
            // Fecha del pedido (calendario): de hoy hasta N días adelante.
            'fecha' => $carrito->fecha(),
            'fechaMin' => today()->subDays(config('softland.fecha_min_dias')),
            'fechaMax' => today()->addDays(config('softland.fecha_max_dias')),
        ]);
    }

    /** Elige el consecutivo de Softland del pedido (solo los DESP-). Responde JSON con el número tentativo. */
    public function consecutivo(Request $request, Carrito $carrito, ConsecutivoPedido $consecutivo)
    {
        $data = $request->validate(['consecutivo' => ['required', 'string', 'max:20']]);

        $disponibles = $consecutivo->disponibles();
        if (! $consecutivo->permitido($data['consecutivo']) || ! isset($disponibles[$data['consecutivo']])) {
            return response()->json(['message' => 'Ese consecutivo no se puede usar para los pedidos.'], 422);
        }

        // El de la configuración se guarda como null (pedido "normal").
        $carrito->fijarConsecutivo($data['consecutivo'] === $consecutivo->codigo() ? null : $data['consecutivo']);

        return response()->json([
            'consecutivo' => $data['consecutivo'],
            'numero' => $disponibles[$data['consecutivo']]['siguiente'],
        ]);
    }

    /** Fija la fecha del pedido elegida en el calendario. Responde JSON. */
    public function fecha(Request $request, Carrito $carrito)
    {
        $min = today()->subDays(config('softland.fecha_min_dias'));
        $max = today()->addDays(config('softland.fecha_max_dias'));
        $data = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$min->format('Y-m-d'), 'before_or_equal:'.$max->format('Y-m-d')],
        ], [
            'fecha.after_or_equal' => 'La fecha del pedido puede ser como mínimo el '.fecha_larga($min).'.',
            'fecha.before_or_equal' => 'La fecha del pedido puede ser como máximo el '.fecha_larga($max).'.',
        ]);

        $carrito->fijarFecha(CarbonImmutable::parse($data['fecha']));

        return response()->json([
            'fecha' => $carrito->fecha()->format('Y-m-d'),
            'etiqueta' => fecha_larga($carrito->fecha()),
            'corta' => fecha_corta($carrito->fecha()),
        ]);
    }

    /**
     * Fija la cantidad de un artículo; 0 lo quita. Responde JSON.
     *
     * "cantidad" va en la unidad de "modo": unidad (viales…) o caja. Sin
     * cantidad y con modo, cambia el modo conservando lo pedido
     * (56 CJA ⇄ 2,800 VIA).
     */
    public function fijar(Request $request, Catalogo $catalogo, Lotes $lotes, Carrito $carrito)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'cantidad' => ['required_without:modo', 'nullable', 'numeric', 'min:0'],
            'modo' => ['nullable', Rule::in([Carrito::UNIDAD, Carrito::CAJA])],
        ]);

        $articulo = $catalogo->encontrar($data['codigo']);

        if (! $articulo) {
            $carrito->quitar($data['codigo']);

            return response()->json([
                'message' => 'El artículo ya no existe en el catálogo.',
                'resumen' => $carrito->resumen(),
            ], 404);
        }

        // Respeta el lote ya elegido; si no hay, el que vence primero.
        $lote = $carrito->loteVigente($articulo->codigo, $lotes->porArticulos([$articulo->codigo])[$articulo->codigo] ?? []);
        $modo = $data['modo'] ?? null;
        $cantidad = $data['cantidad'] ?? null;

        if ($cantidad === null) {
            // Solo cambio de modo: convierte lo que ya había.
            $item = $carrito->items()[$articulo->codigo] ?? null;
            $cantidad = ! $item ? 0 : ($modo === Carrito::UNIDAD
                ? Cantidades::unidades((float) $item['cantidad'], $articulo->factor)
                : (float) $item['cantidad']);
        }

        return $this->aplicar($carrito, $articulo, (float) $cantidad, $lote, $modo);
    }

    /** Cambia el lote de un artículo del pedido (elección manual). Responde JSON. */
    public function lote(Request $request, Catalogo $catalogo, Lotes $lotes, Carrito $carrito)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'lote' => ['required', 'string', 'max:15'],
        ]);

        $item = $carrito->items()[$data['codigo']] ?? null;
        $articulo = $catalogo->encontrar($data['codigo']);
        $lote = collect($lotes->porArticulos([$data['codigo']])[$data['codigo']] ?? [])
            ->first(fn (Lote $l) => $l->lote === $data['lote']);

        if (! $item || ! $articulo || ! $lote) {
            return response()->json([
                'message' => 'Ese lote ya no tiene existencia en la bodega.',
                'resumen' => $carrito->resumen(),
            ], 422);
        }

        return $this->aplicar($carrito, $articulo, Carrito::capturada($item), $lote);
    }

    /**
     * Precio puesto a mano. "por" = unidad (C$32.93 por vial → × factor) o caja.
     * precio null vuelve al precio del nivel del cliente. Responde JSON.
     */
    public function precio(Request $request, Carrito $carrito)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'precio' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'por' => ['nullable', Rule::in([Carrito::UNIDAD, Carrito::CAJA])],
        ], [
            'precio.gt' => 'El precio debe ser mayor que cero.',
        ]);

        $item = $carrito->fijarPrecio(
            $data['codigo'],
            isset($data['precio']) ? (float) $data['precio'] : null,
            $data['por'] ?? Carrito::CAJA,
        );

        if (! $item) {
            return response()->json(['message' => 'El artículo ya no está en el pedido.', 'resumen' => $carrito->resumen()], 404);
        }

        return response()->json($this->linea($item) + ['resumen' => $carrito->resumen()]);
    }

    /**
     * Edición de un artículo del pedido desde su panel: cantidad (en cajas o
     * en unidades), lote y precio, en un solo paso. Responde JSON con la fila
     * ya dibujada. Cantidad 0 lo quita.
     *
     * precio: solo si se cambió (manual); precio_nivel=true vuelve al del nivel.
     */
    public function editar(Request $request, Catalogo $catalogo, Lotes $lotes, Carrito $carrito, Imagenes $imagenes)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'modo' => ['nullable', Rule::in([Carrito::UNIDAD, Carrito::CAJA])],
            'lote' => ['nullable', 'string', 'max:15'],
            'precio' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'por' => ['nullable', Rule::in([Carrito::UNIDAD, Carrito::CAJA])],
            'precio_nivel' => ['nullable', 'boolean'],
        ], [
            'precio.gt' => 'El precio debe ser mayor que cero.',
        ]);

        $articulo = $catalogo->encontrar($data['codigo']);
        if (! $articulo) {
            $carrito->quitar($data['codigo']);

            return response()->json(['message' => 'El artículo ya no existe en el catálogo.', 'eliminado' => true, 'resumen' => $carrito->resumen()], 404);
        }

        $disponibles = $lotes->porArticulos([$articulo->codigo])[$articulo->codigo] ?? [];
        if ($data['lote'] ?? null) {
            $lote = collect($disponibles)->first(fn (Lote $l) => $l->lote === $data['lote']);
            if (! $lote) {
                return response()->json(['message' => "El lote {$data['lote']} ya no tiene existencia en la bodega.", 'resumen' => $carrito->resumen()], 422);
            }
        } else {
            $lote = $carrito->loteVigente($articulo->codigo, $disponibles);
        }

        $pedida = (float) $data['cantidad'];
        $aplicada = $carrito->fijar($articulo, $pedida, $lote, $data['modo'] ?? null);

        if ($aplicada <= 0) {
            return response()->json(['eliminado' => true, 'resumen' => $carrito->resumen()]);
        }

        if ($request->boolean('precio_nivel')) {
            $carrito->fijarPrecio($articulo->codigo, null);
        } elseif (isset($data['precio'])) {
            $carrito->fijarPrecio($articulo->codigo, (float) $data['precio'], $data['por'] ?? Carrito::CAJA);
        }

        $item = $carrito->items()[$articulo->codigo];
        // Se puede pedir más de lo disponible (se completa con traslados): solo se avisa.
        $excede = Carrito::excede($item, $lote) > 0;

        return response()->json([
            'html' => view('carrito._linea', ['i' => $item, 'lotesArt' => $disponibles, 'imagenes' => $imagenes->urls([$articulo->codigo])])->render(),
            'ajustado' => false,
            'excede' => $excede,
            'message' => $excede ? $this->avisoExistencia($articulo, $lote) : null,
            'resumen' => $carrito->resumen(),
        ]);
    }

    public function vaciar(Carrito $carrito)
    {
        $carrito->vaciar();

        return redirect()->route('carrito.index');
    }

    private function aplicar(Carrito $carrito, Articulo $articulo, float $pedida, ?Lote $lote, ?string $modo = null)
    {
        $aplicada = $carrito->fijar($articulo, $pedida, $lote, $modo);
        $item = $carrito->items()[$articulo->codigo] ?? null;
        $enUnidades = $item ? Linea::enUnidades($item) : ($articulo->tieneEmpaque() && $modo !== Carrito::CAJA);

        // Máximo en la unidad de captura.
        $maximo = $enUnidades
            ? ($lote ? $lote->maximoUnidades($articulo->factor) : $articulo->maximoUnidades())
            : ($lote ? $lote->maximoPedible() : $articulo->maximoPedible());
        // No se recorta: se puede pedir más de lo disponible (se completa con traslados); solo se avisa.
        $excede = $item && Carrito::excede($item, $lote) > 0;

        return response()->json([
            'codigo' => $articulo->codigo,
            'cantidad' => $aplicada,
            'maximo' => $maximo, // disponible, solo informativo
            'existencia' => $articulo->existencia,
            'lote' => $lote?->toArray(),
            'ajustado' => false,
            'excede' => $excede,
            'message' => $excede ? $this->avisoExistencia($articulo, $lote) : null,
            'resumen' => $carrito->resumen(),
        ] + ($item ? $this->linea($item) : ['subtotal' => 0, 'modo' => $enUnidades ? Carrito::UNIDAD : Carrito::CAJA]));
    }

    /** "Hay 3,338 CJA en bodega 006 (lote H25124); se pedirá igual." */
    private function avisoExistencia(Articulo $articulo, ?Lote $lote): string
    {
        $disponible = $lote ? $lote->disponible : $articulo->existencia;
        $bodega = config('softland.bodega');

        return $disponible <= 0
            ? "Sin existencia en bodega {$bodega}; se pedirá igual."
            : 'Hay '.cantidad(Cantidades::bajar4($disponible))." {$articulo->unidad} en bodega {$bodega}".($lote ? " (lote {$lote->lote})" : '').'; se pedirá igual.';
    }

    /** Datos de la línea para repintarla (mismos textos que la vista). */
    private function linea(array $item): array
    {
        return [
            'modo' => $item['modo'],
            'cajas' => (float) $item['cantidad'],
            'unidades' => (float) $item['unidades'],
            'unidad_modo' => Linea::unidadModo($item),
            'capturada_texto' => Linea::capturadaTexto($item),
            'equivalencia' => Linea::equivalencia($item),
            'conversion' => Linea::conversion($item),
            'precio' => (float) $item['precio'],
            'precio_unidad' => Linea::precioUnidad($item),
            'precio_nivel' => (float) $item['precio_nivel'],
            'precio_manual' => (bool) $item['precio_manual'],
            'precio_texto' => Linea::precioTexto($item),
            'sin_precio' => Linea::sinPrecio($item),
            'subtotal' => Carrito::subtotal($item),
        ];
    }
}
