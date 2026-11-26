<?php

namespace App\Http\Controllers;

use App\Clientes\Clientes;
use App\Pedidos\Carrito;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDOException;

class ClienteController extends Controller
{
    public function index(Carrito $carrito)
    {
        return view('clientes.index', [
            'actual' => $carrito->cliente(),
            'resumen' => $carrito->resumen(),
        ]);
    }

    /** Fragmento HTML de resultados (se carga con fetch). */
    public function buscar(Request $request, Clientes $clientes, Carrito $carrito)
    {
        $q = trim((string) $request->query('q', ''));
        $error = false;
        $lista = [];

        if (mb_strlen($q) >= 2) {
            try {
                $lista = $clientes->buscar($q);
            } catch (QueryException|PDOException $e) {
                Log::error('Clientes SQL Server: '.$e->getMessage());
                $error = true;
            }
        }

        return view('clientes._resultados', [
            'q' => $q,
            'clientes' => $lista,
            'error' => $error,
            'actual' => $carrito->cliente()?->codigo,
        ]);
    }

    /** Elige el cliente del pedido y pasa a buscar artículos. */
    public function seleccionar(Request $request, Clientes $clientes, Carrito $carrito)
    {
        $codigo = $request->validate(['codigo' => ['required', 'string', 'max:20']])['codigo'];

        $cliente = $clientes->encontrar($codigo);
        if (! $cliente) {
            return back()->with('aviso', 'El cliente no existe o no está activo.');
        }

        $carrito->fijarCliente($cliente);

        return redirect()->route('articulos.index');
    }
}
