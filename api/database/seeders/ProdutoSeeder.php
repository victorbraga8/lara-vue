<?php

namespace Database\Seeders;

use App\Models\Produto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProdutoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $rows = [
            ['nome' => 'Camiseta Básica',        'preco_venda' => 79.90,  'custo_medio' => 40.00, 'estoque' => 10],
            ['nome' => 'Camisa Polo',            'preco_venda' => 129.90, 'custo_medio' => 60.00, 'estoque' => 8],
            ['nome' => 'Calça Jeans Slim',       'preco_venda' => 199.90, 'custo_medio' => 110.00,'estoque' => 12],
            ['nome' => 'Bermuda Sarja',          'preco_venda' => 149.90, 'custo_medio' => 70.00, 'estoque' => 9],
            ['nome' => 'Jaqueta Jeans',          'preco_venda' => 249.90, 'custo_medio' => 140.00,'estoque' => 5],
            ['nome' => 'Moletom Canguru',        'preco_venda' => 189.90, 'custo_medio' => 95.00, 'estoque' => 7],
            ['nome' => 'Tênis Casual',           'preco_venda' => 299.90, 'custo_medio' => 160.00,'estoque' => 6],
            ['nome' => 'Meia Cano Médio (par)',  'preco_venda' => 19.90,  'custo_medio' => 6.50,  'estoque' => 50],
            ['nome' => 'Boné Trucker',           'preco_venda' => 69.90,  'custo_medio' => 28.00, 'estoque' => 15],
            ['nome' => 'Cinto Couro',            'preco_venda' => 89.90,  'custo_medio' => 35.00, 'estoque' => 10],
        ];

        $rows = array_map(function ($r) use ($now) {
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
            return $r;
        }, $rows);

        Produto::upsert(
            $rows,
            ['nome'],
            ['preco_venda','custo_medio','estoque','updated_at']
        );
    }
}
