<?php

use App\Http\Controllers\ArticuloController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ImagenController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\SoftlandController;
use App\Http\Middleware\RequiereCliente;
use App\Pedidos\Carrito;
use Illuminate\Support\Facades\Route;

// Inicio: primero el cliente, después los artículos.
Route::get('/', fn (Carrito $carrito) => redirect()->route(
    $carrito->cliente() ? 'articulos.index' : 'clientes.index'
));

// 1. Cliente del pedido
Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
Route::post('/clientes/seleccionar', [ClienteController::class, 'seleccionar'])->name('clientes.seleccionar');

// 2. Artículos y 3. pedido: requieren cliente elegido
Route::middleware(RequiereCliente::class)->group(function () {
    Route::get('/articulos', [ArticuloController::class, 'index'])->name('articulos.index');
    Route::get('/articulos/buscar', [ArticuloController::class, 'buscar'])->name('articulos.buscar');

    Route::get('/pedido', [CarritoController::class, 'index'])->name('carrito.index');
    Route::post('/pedido/items', [CarritoController::class, 'fijar'])->name('carrito.fijar');
    Route::post('/pedido/items/lote', [CarritoController::class, 'lote'])->name('carrito.lote');
    Route::delete('/pedido', [CarritoController::class, 'vaciar'])->name('carrito.vaciar');
    // Vista previa (solo lectura) de lo que llevaría el pedido en Softland.
    Route::get('/pedido/softland', [SoftlandController::class, 'vistaPrevia'])->name('softland.vista-previa');

    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
});

Route::get('/img/{codigo}', [ImagenController::class, 'miniatura'])
    ->where('codigo', '[A-Za-z0-9._-]+')
    ->name('imagenes.miniatura');

// Historial
Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
// Modificar un pedido guardado (solo si no se envió a Softland).
Route::post('/pedidos/{pedido}/editar', [PedidoController::class, 'editar'])->name('pedidos.editar');
Route::post('/pedido/cancelar-edicion', [PedidoController::class, 'cancelarEdicion'])->name('pedidos.cancelar-edicion');
// Envío manual a Softland (con confirmación SweetAlert2 en la pantalla).
Route::post('/pedidos/{pedido}/softland', [SoftlandController::class, 'enviar'])->name('pedidos.softland');
