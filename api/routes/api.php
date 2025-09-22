<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\VendaController;

Route::get('/health', fn () => response()->json(['message' => 'Endpoint funcionando']));

// Produtos
Route::get('/produtos', [ProdutoController::class, 'index']);
Route::post('/produtos', [ProdutoController::class, 'store']);

// Compras
Route::get('/compras', [CompraController::class, 'index']);
Route::get('/compras/{id}', [CompraController::class, 'show']);
Route::post('/compras', [CompraController::class, 'store']);

// Vendas
Route::get('/vendas', [VendaController::class, 'index']);
Route::get('/vendas/{id}', [VendaController::class, 'show']);
Route::post('/vendas', [VendaController::class, 'store']);
Route::post('/vendas/{id}/cancelar', [VendaController::class, 'cancelar']);