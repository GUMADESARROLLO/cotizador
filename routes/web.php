<?php

use App\Http\Controllers\ArticuloController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ImagenController;
use App\Http\Controllers\PedidoController;
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
    Route::delete('/pedido', [CarritoController::class, 'vaciar'])->name('carrito.vaciar');

    Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
});

Route::get('/img/{codigo}', [ImagenController::class, 'miniatura'])
    ->where('codigo', '[A-Za-z0-9._-]+')
    ->name('imagenes.miniatura');

// Historial
Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
