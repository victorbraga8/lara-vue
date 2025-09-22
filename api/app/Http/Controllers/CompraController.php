<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompraRequest;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    /**
     * GET /api/compras  (paginado + filtro por fornecedor)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page') ?: 15;

        $query = Compra::query()
            ->select(['id', 'fornecedor', 'total', 'created_at'])
            ->when($request->filled('fornecedor'), function ($q) use ($request) {
                $term = preg_replace('/\s+/', ' ', trim((string) $request->input('fornecedor')));
                $q->where('fornecedor', 'like', "%{$term}%");
            })
            ->orderByDesc('id');

        $page = $query->paginate($perPage);

        return response()->json($page);
    }

    /**
     * GET /api/compras/{id}
     */
    public function show(int $id): JsonResponse
    {
        $compra = Compra::with(['itens' => function ($q) {
            $q->select(['id','compra_id','produto_id','quantidade','preco_unitario','subtotal']);
        }])->find($id);

        if (! $compra) {
            return response()->json(['message' => 'Compra não encontrada.'], 404);
        }

        return response()->json($compra);
    }

    /**
     * POST /api/compras
     * - Transação
     * - Cria compra + itens
     * - Atualiza estoque e custo_medio dos produtos
     */
    public function store(StoreCompraRequest $request): JsonResponse
    {
        $payload = $request->validated();

        // Normaliza fornecedor (trim + colapsa espaços)
        $fornecedor = preg_replace('/\s+/', ' ', trim((string) $payload['fornecedor']));

        // Mescla itens duplicados por produto_id (somando quantidades e custo total)
        $itensRaw = $payload['produtos'];
        $agrupados = [];
        foreach ($itensRaw as $item) {
            $pid = (int) $item['id'];
            $qtd = (int) $item['quantidade'];
            $pre = (float) $item['preco_unitario'];
            if (!isset($agrupados[$pid])) {
                $agrupados[$pid] = ['quantidade' => 0, 'custo_total' => 0.0];
            }
            $agrupados[$pid]['quantidade']  += $qtd;
            $agrupados[$pid]['custo_total'] += $qtd * $pre;
        }

        $compraResult = DB::transaction(function () use ($fornecedor, $agrupados, $itensRaw) {
            // Cria compra com total 0 (atualiza ao final)
            $compra = Compra::create([
                'fornecedor' => $fornecedor,
                'total'      => 0,
            ]);

            $totalCompra = 0.0;

            // Persistir itens exatamente como vieram (sem mesclar na tabela),
            // mas usar os "agrupados" para atualizar custo/estoque de forma eficiente.
            foreach ($itensRaw as $item) {
                $pid  = (int) $item['id'];
                $qtd  = (int) $item['quantidade'];
                $pre  = (float) $item['preco_unitario'];
                $sub  = $qtd * $pre;

                CompraItem::create([
                    'compra_id'     => $compra->id,
                    'produto_id'    => $pid,
                    'quantidade'    => $qtd,
                    'preco_unitario'=> $pre,
                    'subtotal'      => $sub,
                ]);

                $totalCompra += $sub;
            }

            // Atualizar estoque e custo_medio — por produto (agrupado)
            $produtosAtualizados = [];
            $produtos = Produto::whereIn('id', array_keys($agrupados))->lockForUpdate()->get()->keyBy('id');

            foreach ($agrupados as $pid => $info) {
                /** @var Produto $prod */
                $prod = $produtos[$pid];

                $estoqueAntigo = (int) $prod->estoque;
                $custoAntigo   = (float) $prod->custo_medio;
                $qtdCompra     = (int) $info['quantidade'];
                $custoAdicional= (float) $info['custo_total'];

                $novoEstoque = $estoqueAntigo + $qtdCompra;

                // custo_medio ponderado: ((old_stock*old_cost) + total_compra_prod) / novo_estoque
                $novoCusto = $novoEstoque > 0
                    ? (($estoqueAntigo * $custoAntigo) + $custoAdicional) / $novoEstoque
                    : 0.0;

                // arredonda 2 casas
                $novoCusto = round($novoCusto, 2);

                $prod->update([
                    'estoque'     => $novoEstoque,
                    'custo_medio' => $novoCusto,
                ]);

                $produtosAtualizados[] = [
                    'id'          => $prod->id,
                    'estoque'     => $prod->estoque,
                    'custo_medio' => number_format((float)$prod->custo_medio, 2, '.', ''),
                ];
            }

            // Atualiza total da compra
            $compra->update([
                'total' => round($totalCompra, 2),
            ]);

            return [$compra->fresh('itens'), $produtosAtualizados];
        });

        /** @var Compra $compra */
        [$compra, $produtosAtualizados] = $compraResult;

        return response()->json([
            'message'              => 'Compra registrada com sucesso.',
            'compra'               => [
                'id'         => $compra->id,
                'fornecedor' => $compra->fornecedor,
                'total'      => $compra->total,
                'itens'      => $compra->itens->map(function ($i) {
                    return [
                        'produto_id'    => $i->produto_id,
                        'quantidade'    => $i->quantidade,
                        'preco_unitario'=> $i->preco_unitario,
                        'subtotal'      => $i->subtotal,
                    ];
                })->values(),
            ],
            'produtos_atualizados' => $produtosAtualizados,
        ], 201);
    }
}
