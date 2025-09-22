<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    protected $fillable = ['fornecedor', 'total'];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(CompraItem::class);
    }
}
