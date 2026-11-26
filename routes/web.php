<?php

use App\Http\Controllers\ArticuloController;
use App\Http\Controllers\CarritoController;
use App\Http\Controllers\PedidoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ArticuloController::class, 'index'])->name('articulos.index');
Route::get('/articulos/buscar', [ArticuloController::class, 'buscar'])->name('articulos.buscar');

Route::get('/pedido', [CarritoController::class, 'index'])->name('carrito.index');
Route::post('/pedido/items', [CarritoController::class, 'fijar'])->name('carrito.fijar');
Route::delete('/pedido', [CarritoController::class, 'vaciar'])->name('carrito.vaciar');

Route::get('/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
Route::post('/pedidos', [PedidoController::class, 'store'])->name('pedidos.store');
Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
