<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SensorData extends Model
{
    use HasFactory;

    protected $table = 'sensor_data';

    protected $fillable = [
        'device_id',
        'timestamp',
        'humidity',
        'temperature_c',
        'temperature_f',
        'co_ppm',
        'nh3_ppm',
        'no2_ppm',
        'hydrocarbon_ppm',
        // Arduino tidak mengukur CO2, CH4, N2O langsung - hanya CO, NH3, NO2
        // 'co2_ppm',
        // 'ch4_ppm', // Tidak digunakan lagi
        // 'n2o_ppm', // Tidak digunakan lagi
        'pm_density',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'humidity' => 'float',
        'temperature_c' => 'float',
        'temperature_f' => 'float',
        'co_ppm' => 'float',
        'nh3_ppm' => 'float',
        'no2_ppm' => 'float',
        'hydrocarbon_ppm' => 'float',
        // Arduino tidak mengukur CO2, CH4, N2O langsung - hanya CO, NH3, NO2
        // 'co2_ppm' => 'float',
        // 'ch4_ppm' => 'float', // Tidak digunakan lagi
        // 'n2o_ppm' => 'float', // Tidak digunakan lagi
        'pm_density' => 'float',
    ];

    /**
     * Relasi ke CarbonCredit berdasarkan device_id
     */
    public function carbonCredit()
    {
        return $this->belongsTo(CarbonCredit::class, 'device_id', 'device_id');
    }

    /**
     * Relasi ke Co2eData
     */
    public function co2eData()
    {
        return $this->hasMany(Co2eData::class, 'device_id', 'device_id')
                    ->where('timestamp', $this->timestamp);
    }

    /**
     * Relasi ke GpsData
     */
    public function gpsData()
    {
        return $this->hasMany(GpsData::class, 'device_id', 'device_id')
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
}
