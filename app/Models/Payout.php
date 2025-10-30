<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'payout_id',
        'amount',
        'admin_fee',
        'net_amount',
        'status',
        'processed_at',
        'notes',
        'midtrans_payout_id',
        'midtrans_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'pending' => 'secondary',
            'created' => 'info',
            'processing' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get status label in Bahasa Indonesia
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending' => 'Menunggu',
            'created' => 'Dibuat',
            'processing' => 'Diproses',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            default => 'Tidak Diketahui'
        };
    }

    /**
     * Check if payout can be processed
     */
    public function canBeProcessed()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payout can be approved
     */
    public function canBeApproved()
    {
        return $this->status === 'created';
    }

    /**
     * Check if payout is in final state
     */
    public function isFinal()
    {
        return in_array($this->status, ['completed', 'failed']);
    }
}