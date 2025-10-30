<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpsData extends Model
{
    use HasFactory;

    protected $table = 'gps_data';

    protected $fillable = [
        'device_id',
        'timestamp',
        'latitude',
        'longitude',
        'speed_kmph',
        'date_recorded',
        'time_recorded',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'speed_kmph' => 'float',
    ];

    /**
     * Relasi ke CarbonCredit berdasarkan device_id
     */
    public function carbonCredit()
    {
        return $this->belongsTo(CarbonCredit::class, 'device_id', 'device_id');
    }

    /**
     * Relasi ke SensorData
     */
    public function sensorData()
    {
        return $this->belongsTo(SensorData::class, 'device_id', 'device_id')
                    ->where('timestamp', $this->timestamp);
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
     * Scope untuk data hari ini
     */
    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', today());
    }

    /**
     * Scope untuk data bulan ini
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('timestamp', now()->month)
                    ->whereYear('timestamp', now()->year);
    }

    /**
     * Scope untuk data dengan koordinat valid
     */
    public function scopeValidCoordinates($query)
    {
        return $query->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0);
    }

    /**
     * Hitung jarak dari koordinat tertentu (dalam km)
     */
    public function distanceFrom($latitude, $longitude)
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        $earthRadius = 6371; // Radius bumi dalam km

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Format koordinat untuk display
     */
    public function getFormattedCoordinatesAttribute()
    {
        if (!$this->latitude || !$this->longitude) {
            return 'Koordinat tidak tersedia';
        }

        return sprintf('%.6f, %.6f', $this->latitude, $this->longitude);
    }
}
