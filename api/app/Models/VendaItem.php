<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaItem extends Model
{
    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'produto_id',
        'quantidade',
        'preco_unitario',
        'custo_medio_snapshot',
        'subtotal',
        'lucro_item',
    ];

    protected $casts = [
        'preco_unitario'        => 'decimal:2',
        'custo_medio_snapshot'  => 'decimal:2',
        'subtotal'              => 'decimal:2',
        'lucro_item'            => 'decimal:2',
        'quantidade'            => 'integer',
    ];

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Produto::class);
    }
}
