<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'buy_order_id',
        'sell_order_id',
        'buyer_id',
        'seller_id',
        'symbol',
        'price',
        'amount',
        'total',
        'commission',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'amount' => 'decimal:8',
        'total' => 'decimal:8',
        'commission' => 'decimal:8',
    ];
}
