<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venda extends Model
{
    protected $fillable = ['cliente', 'total', 'lucro', 'status', 'canceled_at'];

    protected $casts = [
        'total'       => 'decimal:2',
        'lucro'       => 'decimal:2',
        'canceled_at' => 'datetime',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }
}
