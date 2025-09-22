<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    protected $fillable = [
        'nome', 'preco_venda','estoque', 
    ];

    protected $casts = [
        'custo_medio' => 'decimal:2',
        'preco_venda' => 'decimal:2',
        'estoque'     => 'integer',
    ];
}
