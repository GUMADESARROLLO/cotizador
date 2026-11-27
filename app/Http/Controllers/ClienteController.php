<?php

namespace App\Http\Controllers;

use App\Clientes\Clientes;
use App\Models\Pedido;
use App\Pedidos\Carrito;
use App\Support\Orden;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PDOException;

class ClienteController extends Controller
{
    private const POR_PAGINA = [10, 25, 50];

    private const FILTROS = ['todos', 'morosos', 'saldo'];

    // Columnas que se ordenan al tocar el encabezado.
    private const ORDENES = ['cliente', 'nivel', 'limite', 'saldo', 'disponible', 'telefonos'];

    public function index(Request $request, Clientes $clientes, Carrito $carrito)
    {
        return view('clientes.index', [
            'actual' => $carrito->cliente(),
            'resumen' => $carrito->resumen(),
            'recientes' => $this->recientes(),
            // Primera página en el servidor, con los mismos params que usa el fetch (q, f, page, per).
            'listado' => $this->listado($request, $clientes),
        ]);
    }

    /**
     * Últimos 3 clientes a los que se les montó pedido (sin repetir), para
     * elegirlos de un toque sin buscarlos.
     *
     * @return list<array{codigo:string, nombre:string, fecha:Carbon}>
     */
    private function recientes(): array
    {
        return Pedido::query()
            ->whereNotNull('cliente_codigo')
            ->latest()
            ->limit(50)
            ->get(['cliente_codigo', 'cliente', 'created_at'])
            ->unique('cliente_codigo')
            ->take(3)
            ->map(fn (Pedido $p) => ['codigo' => (string) $p->cliente_codigo, 'nombre' => $p->cliente, 'fecha' => $p->created_at])
            ->values()
            ->all();
    }

    /** Fragmento HTML de resultados (se carga con fetch). */
    public function buscar(Request $request, Clientes $clientes, Carrito $carrito)
    {
        return view('clientes._resultados', $this->listado($request, $clientes) + [
            'actual' => $carrito->cliente()?->codigo,
        ]);
    }

    /**
     * Página de la cartera del vendedor según q, f, per y page.
     *
     * @return array<string, mixed>
     */
    private function listado(Request $request, Clientes $clientes): array
    {
        $q = trim((string) $request->query('q', ''));
        $f = (string) $request->query('f', 'todos');
        $f = in_array($f, self::FILTROS, true) ? $f : 'todos';
        $per = (int) $request->query('per', self::POR_PAGINA[1]);
        $per = in_array($per, self::POR_PAGINA, true) ? $per : self::POR_PAGINA[1];
        $pagina = max(1, (int) $request->query('page', 1));

        $orden = Orden::desde($request, self::ORDENES);
        $datos = ['q' => $q, 'f' => $f, 'orden' => $orden, 'per' => $per, 'pagina' => $pagina, 'clientes' => [], 'total' => 0, 'paginas' => 1, 'contadores' => null, 'error' => false];

        try {
            $datos['contadores'] = $clientes->contar($q);
            $total = $datos['contadores'][['morosos' => 'morosos', 'saldo' => 'saldo'][$f] ?? 'todos'];
            $datos['paginas'] = max(1, (int) ceil($total / $per));
            $datos['pagina'] = min($pagina, $datos['paginas']);

            $r = $clientes->listar($q, $f, $datos['pagina'], $per, $orden);
            $datos['clientes'] = $r['items'];
            $datos['total'] = $r['total'];
        } catch (QueryException|PDOException $e) {
            Log::error('Clientes SQL Server: '.$e->getMessage());
            $datos['error'] = true;
        }

        return $datos;
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
