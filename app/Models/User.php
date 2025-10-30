<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'nomor_kartu_keluarga',
        'nik_e_ktp',
        'password',
        'role',
        'phone_number',
        'address',
        'bank_account',
        'bank_name',  
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function carbonCredits()
    {
        return $this->hasMany(CarbonCredit::class, 'owner_id');
    }

    public function vehicles()
    {
        return $this->hasMany(CarbonCredit::class, 'owner_id')->whereNotNull('vehicle_type');
    }

    public function soldTransactions()
    {
        return $this->hasMany(Transaction::class, 'seller_id');
    }

    public function boughtTransactions()
    {
        return $this->hasMany(Transaction::class, 'buyer_id');
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class, 'user_id');
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isUser()
    {
        return $this->role === 'user';
    }
}
