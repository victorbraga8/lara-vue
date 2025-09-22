<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProdutoRequest;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;

class ProdutoController extends Controller
{
    public function index(): JsonResponse
    {
        $produtos = Produto::query()
            ->select(['id', 'nome', 'custo_medio', 'preco_venda', 'estoque'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($produtos);
    }

    public function store(StoreProdutoRequest $request): JsonResponse
    {

        $usouEstoquePadrao = !$request->has('estoque');
        $estoque = $usouEstoquePadrao ? 1 : (int) $request->input('estoque');

        $produto = Produto::create([
            'nome'         => $request->string('nome'),
            'preco_venda'  => $request->input('preco_venda'),
            'custo_medio'  => $request->input('custo_medio'),
            'estoque'      => $estoque,
        ])->refresh(); 

        
        $msg = 'Produto cadastrado com sucesso.';
        if ($usouEstoquePadrao) {
            $msg .= ' Estoque não informado: cadastrado com valor padrão 1.';
        }

        return response()->json([
            'message' => 'Produto cadastrado com sucesso.',
            'data'    => [
                'id'          => $produto->id,
                'nome'        => $produto->nome,
                'custo_medio' => $produto->custo_medio,
                'preco_venda' => $produto->preco_venda,
                'estoque'     => $produto->estoque,
            ],
        ], 201);
    }
}
