<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'buyer_id',
        'transaction_id',
        'type',
        'amount',
        'price_per_unit',
        'total_amount',
        'status',
        'paid_at',
        'completed_at',
        'payment_method',
        'midtrans_transaction_id',
        'midtrans_snap_token',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'price_per_unit' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function payout()
    {
        return $this->hasOne(Payout::class);
    }
}
