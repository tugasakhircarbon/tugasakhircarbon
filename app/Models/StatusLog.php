<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StatusLog extends Model
{
    use HasFactory;

    protected $table = 'status_log';

    protected $fillable = [
        'device_id',
        'timestamp',
        'status',
        'ip_address',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    /**
     * Relasi ke CarbonCredit berdasarkan device_id
     */
    public function carbonCredit()
    {
        return $this->belongsTo(CarbonCredit::class, 'device_id', 'device_id');
    }

    /**
     * Scope untuk filter berdasarkan device
     */
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope untuk data terbaru
     */
    public function scopeLatest($query, $limit = 10)
    {
        return $query->orderBy('timestamp', 'desc')->limit($limit);
    }

    /**
     * Scope untuk status tertentu
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', 'like', '%' . $status . '%');
    }

    /**
     * Scope untuk data hari ini
     */
    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', today());
    }

    /**
     * Scope untuk status online/active
     */
    public function scopeOnline($query)
    {
        return $query->where('status', 'like', '%online%')
                    ->orWhere('status', 'like', '%active%')
                    ->orWhere('status', 'like', '%connected%');
    }

    /**
     * Scope untuk status offline/error
     */
    public function scopeOffline($query)
    {
        return $query->where('status', 'like', '%offline%')
                    ->orWhere('status', 'like', '%error%')
                    ->orWhere('status', 'like', '%disconnected%');
    }

    /**
     * Check apakah device sedang online berdasarkan log terbaru
     */
    public static function isDeviceOnline($deviceId, $timeoutMinutes = 5)
    {
        $latestLog = static::forDevice($deviceId)
                          ->latest()
                          ->first();

        if (!$latestLog) {
            return false;
        }

        // Check apakah log terbaru dalam rentang waktu yang ditentukan
        $isRecent = $latestLog->timestamp->diffInMinutes(now()) <= $timeoutMinutes;
        
        // Check apakah status menunjukkan online
        $isOnlineStatus = stripos($latestLog->status, 'online') !== false ||
                         stripos($latestLog->status, 'active') !== false ||
                         stripos($latestLog->status, 'connected') !== false;

        return $isRecent && $isOnlineStatus;
    }

    /**
     * Get status terakhir untuk device
     */
    public static function getLastStatus($deviceId)
    {
        $latestLog = static::forDevice($deviceId)
                          ->latest()
                          ->first();

        return $latestLog ? $latestLog->status : 'Unknown';
    }
}
