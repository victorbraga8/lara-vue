<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendaRequest;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VendaController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page') ?: 15;

        $query = Venda::query()
            ->select(['id', 'cliente', 'total', 'lucro', 'status', 'created_at'])
            ->when($request->filled('cliente'), function ($q) use ($request) {
                $term = preg_replace('/\s+/', ' ', trim((string) $request->input('cliente')));
                $q->where('cliente', 'like', "%{$term}%");
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', (string) $request->input('status')); 
            })
            ->orderByDesc('id');

        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $venda = Venda::with(['itens' => function ($q) {
            $q->select([
                'id','venda_id','produto_id','quantidade',
                'preco_unitario','custo_medio_snapshot','subtotal','lucro_item'
            ]);
        }])->find($id);

        if (! $venda) {
            return response()->json(['message' => 'Venda não encontrada.'], 404);
        }

        return response()->json($venda);
    }

    /**
     * POST /api/vendas
     * - Valida estoque por produto (agrupado)
     * - Cria venda + itens (snapshot do custo_medio)
     * - Baixa estoque
     * - Calcula total e lucro
     */
    public function store(StoreVendaRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $cliente = preg_replace('/\s+/', ' ', trim((string) $payload['cliente']));

        // Agrupar por produto para validar estoque total
        $itensRaw = $payload['produtos'];
        $agrupados = [];
        foreach ($itensRaw as $item) {
            $pid = (int) $item['id'];
            $qtd = (int) $item['quantidade'];
            $pre = (float) $item['preco_unitario'];
            if (!isset($agrupados[$pid])) {
                $agrupados[$pid] = ['quantidade' => 0, 'preco_medio' => 0, 'soma_preco' => 0];
            }
            $agrupados[$pid]['quantidade'] += $qtd;
            $agrupados[$pid]['soma_preco']  += $pre; 
        }

        // Transação: lock nos produtos, valida estoque e executa baixa
        $result = DB::transaction(function () use ($cliente, $itensRaw, $agrupados) {
            // Carregar produtos com lock
            $produtos = Produto::whereIn('id', array_keys($agrupados))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Validar estoque suficiente
            $erros = [];
            foreach ($agrupados as $pid => $info) {
                if (!isset($produtos[$pid])) {
                    $erros[] = "Produto {$pid} não encontrado.";
                    continue;
                }
                $estoque = (int) $produtos[$pid]->estoque;
                if ($estoque < $info['quantidade']) {
                    $erros[] = "Estoque insuficiente para o produto ID {$pid}. Disponível: {$estoque}, solicitado: {$info['quantidade']}.";
                }
            }
            if (!empty($erros)) {
                abort(response()->json([
                    'message' => 'Estoque insuficiente.',
                    'errors'  => ['produtos' => $erros]
                ], 422));
            }

            // Criar venda (total/lucro = 0 inicialmente)
            $venda = Venda::create([
                'cliente' => $cliente,
                'total'   => 0,
                'lucro'   => 0,
                'status'  => 'completed',
            ]);

            $totalVenda = 0.0;
            $lucroVenda = 0.0;

            // Persistir itens exatamente como vieram (sem mesclar)
            foreach ($itensRaw as $item) {
                $pid  = (int) $item['id'];
                $qtd  = (int) $item['quantidade'];
                $pre  = (float) $item['preco_unitario'];

                $produto = $produtos[$pid];
                $custoSnapshot = (float) $produto->custo_medio;

                $subtotal = $qtd * $pre;
                $lucroItem = $qtd * ($pre - $custoSnapshot);

                VendaItem::create([
                    'venda_id'             => $venda->id,
                    'produto_id'           => $pid,
                    'quantidade'           => $qtd,
                    'preco_unitario'       => $pre,
                    'custo_medio_snapshot' => round($custoSnapshot, 2),
                    'subtotal'             => round($subtotal, 2),
                    'lucro_item'           => round($lucroItem, 2),
                ]);

                $totalVenda += $subtotal;
                $lucroVenda += $lucroItem;
            }

            // Baixa de estoque por produto
            foreach ($agrupados as $pid => $info) {
                $produto = $produtos[$pid];
                $novoEstoque = (int) $produto->estoque - (int) $info['quantidade'];
                $produto->update(['estoque' => $novoEstoque]);
            }

            // Atualiza venda com totais arredondados
            $venda->update([
                'total' => round($totalVenda, 2),
                'lucro' => round($lucroVenda, 2),
            ]);

            return $venda->fresh('itens');
        });

        /** @var Venda $venda */
        $venda = $result;

        return response()->json([
            'message' => 'Venda registrada com sucesso.',
            'venda'   => [
                'id'       => $venda->id,
                'cliente'  => $venda->cliente,
                'status'   => $venda->status,
                'total'    => $venda->total,
                'lucro'    => $venda->lucro,
                'itens'    => $venda->itens->map(function ($i) {
                    return [
                        'produto_id'           => $i->produto_id,
                        'quantidade'           => $i->quantidade,
                        'preco_unitario'       => $i->preco_unitario,
                        'custo_medio_snapshot' => $i->custo_medio_snapshot,
                        'subtotal'             => $i->subtotal,
                        'lucro_item'           => $i->lucro_item,
                    ];
                })->values(),
            ],
        ], 201);
    }

    /**
     * POST /api/vendas/{id}/cancelar
     * - Reverte estoque com base nos itens
     * - Não altera custo_medio
     * - Não permite cancelar 2x
     */
    public function cancelar(int $id): JsonResponse
    {
        $venda = Venda::with('itens')->find($id);
        if (! $venda) {
            return response()->json(['message' => 'Venda não encontrada.'], 404);
        }
        if ($venda->status === 'canceled') {
            return response()->json(['message' => 'Venda já cancelada.'], 409);
        }

        DB::transaction(function () use ($venda) {
            $prodIds = $venda->itens->pluck('produto_id')->unique()->values()->all();
            $produtos = Produto::whereIn('id', $prodIds)->lockForUpdate()->get()->keyBy('id');

            // devolver estoque
            foreach ($venda->itens as $item) {
                $p = $produtos[$item->produto_id];
                $p->update(['estoque' => (int)$p->estoque + (int)$item->quantidade]);
            }

            $venda->update([
                'status'      => 'canceled',
                'canceled_at' => Carbon::now(),
            ]);
        });

        return response()->json(['message' => 'Venda cancelada e estoque revertido.']);
    }
}
