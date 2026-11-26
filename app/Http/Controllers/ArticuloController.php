<?php

namespace App\Http\Controllers;

use App\Catalogo\Catalogo;
use App\Pedidos\Carrito;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;

class ArticuloController extends Controller
{
    public function index(Carrito $carrito)
    {
        return view('articulos.index', ['resumen' => $carrito->resumen()]);
    }

    /** Devuelve el fragmento HTML de resultados (se carga con fetch). */
    public function buscar(Request $request, Catalogo $catalogo, Carrito $carrito)
    {
        $q = trim((string) $request->query('q', ''));
        $error = false;
        $articulos = [];

        if (mb_strlen($q) >= 2) {
            try {
                $articulos = $catalogo->buscar($q);
            } catch (QueryException|PDOException $e) {
                Log::error('Catálogo SQL Server: '.$e->getMessage());
                $error = true;
            }
        }

        return view('articulos._resultados', [
            'q' => $q,
            'articulos' => $articulos,
            'error' => $error,
            'enCarrito' => array_map(fn ($i) => $i['cantidad'], $carrito->items()),
        ]);
    }
}
