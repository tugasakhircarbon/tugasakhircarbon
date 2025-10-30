<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'carbon_credit_id',
        'amount',
        'price',
        'vehicle_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function carbonCredit()
    {
        return $this->belongsTo(CarbonCredit::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(CarbonCredit::class, 'vehicle_id');
    }
}
