<?php

namespace App\Http\Controllers;

use App\Catalogo\Catalogo;
use App\Catalogo\Imagenes;
use App\Catalogo\Reorden;
use App\Pedidos\Carrito;
use App\Support\Orden;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;

class ArticuloController extends Controller
{
    private const POR_PAGINA = [10, 20, 50];

    // El primero es el filtro por defecto: solo lo que se puede vender.
    private const FILTROS = ['existencia', 'todos', 'prioridad'];

    // Columnas que se ordenan al tocar el encabezado.
    private const ORDENES = ['articulo', 'categoria', 'meses', 'existencia', 'precio'];

    /** Pantalla completa: la primera página se renderiza en servidor con los mismos params de la URL. */
    public function index(Request $request, Carrito $carrito, Catalogo $catalogo, Imagenes $imagenes, Reorden $reorden)
    {
        return view('articulos.index', [
            'resumen' => $carrito->resumen(),
            'cliente' => $carrito->cliente(),
            'prioridadDisponible' => $reorden->cargado(),
            'lista' => $this->lista($request, $catalogo, $carrito, $imagenes, $reorden),
        ]);
    }

    /** Devuelve el fragmento HTML de resultados (se carga con fetch). */
    public function buscar(Request $request, Catalogo $catalogo, Carrito $carrito, Imagenes $imagenes, Reorden $reorden)
    {
        return view('articulos._resultados', $this->lista($request, $catalogo, $carrito, $imagenes, $reorden));
    }

    /** Datos de la vista de resultados (catálogo paginado con filtros). */
    private function lista(Request $request, Catalogo $catalogo, Carrito $carrito, Imagenes $imagenes, Reorden $reorden): array
    {
        $q = trim((string) $request->query('q', ''));
        $filtro = in_array($request->query('f'), self::FILTROS, true) ? $request->query('f') : self::FILTROS[0];
        $porPagina = (int) $request->query('per', 20);
        $porPagina = in_array($porPagina, self::POR_PAGINA, true) ? $porPagina : 20;
        $pagina = max(1, (int) $request->query('page', 1));
        $orden = Orden::desde($request, self::ORDENES);
        // Categoría y meses no están en el catálogo: sus valores salen del mapa en caché.
        $valoresOrden = in_array($orden?->campo, ['categoria', 'meses'], true) ? $reorden->valores($orden->campo) : [];

        // "Prioridad A" necesita el mapa de categorías; sin él, el filtro no aplica.
        if ($filtro === 'prioridad' && ! $reorden->cargado()) {
            $filtro = self::FILTROS[0];
        }

        $articulos = [];
        $total = 0;
        $error = false;

        try {
            $prioridad = $filtro === 'prioridad' ? $reorden->codigosConCategoria('A') : [];
            $res = $catalogo->listar($q, $filtro, $pagina, $porPagina, $prioridad, $orden, $valoresOrden);

            // Página fuera de rango (p. ej. tras cambiar el filtro): se vuelve a la última.
            $ultima = max(1, (int) ceil($res['total'] / $porPagina));
            if ($pagina > $ultima) {
                $pagina = $ultima;
                $res = $catalogo->listar($q, $filtro, $pagina, $porPagina, $prioridad, $orden, $valoresOrden);
            }
            $articulos = $res['items'];
            $total = $res['total'];
        } catch (QueryException|PDOException $e) {
            Log::error('Catálogo SQL Server: '.$e->getMessage());
            $error = true;
        }

        $codigos = array_map(fn ($a) => $a->codigo, $articulos);

        return [
            'q' => $q,
            'filtro' => $filtro,
            'orden' => $orden,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'total' => $total,
            'articulos' => $articulos,
            'error' => $error,
            'imagenes' => $imagenes->urls($codigos),
            // Mapa en caché: no consulta SQL Server (ver App\Catalogo\Reorden).
            'reorden' => $codigos ? $reorden->para($codigos) : [],
            'reordenCargado' => $codigos && $reorden->cargado(),
            'reordenFecha' => $codigos ? $reorden->fechaCategoria() : null,
            'enCarrito' => $carrito->items(),
        ];
    }
}
