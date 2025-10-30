<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Co2eData extends Model
{
    use HasFactory;

    protected $table = 'co2e_data';

    protected $fillable = [
        'device_id',
        'timestamp',
        // 'co2e_ppm', // Tidak diperlukan lagi, menggunakan co2e_mg_m3
        // Arduino menggunakan CO, NH3, NO2 - tidak ada CO2, CH4, N2O langsung
        'co_contribution',
        'nh3_contribution',
        'no2_contribution',
        'gwp_co',
        'gwp_nh3',
        'gwp_no2',
        // Mass concentrations untuk debugging
        'co_mg_m3',
        'nh3_mg_m3',
        'no2_mg_m3',
        // Total CO2e dalam mg/m³
        'co2e_mg_m3',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        // 'co2e_ppm' => 'float', // Tidak diperlukan lagi
        'co_contribution' => 'float',
        'nh3_contribution' => 'float',
        'no2_contribution' => 'float',
        'gwp_co' => 'float',
        'gwp_nh3' => 'float',
        'gwp_no2' => 'float',
        'co_mg_m3' => 'float',
        'nh3_mg_m3' => 'float',
        'no2_mg_m3' => 'float',
        'co2e_mg_m3' => 'float',
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
     * Hitung total emisi dalam kg berdasarkan mg/m³
     */
    public function getEmissionInKgAttribute()
    {
        // Konversi langsung dari mg/m³ ke kg
        return self::convertMgM3ToKg($this->co2e_mg_m3 ?? 0);
    }

    /**
     * 🔥 STATIC METHOD: Hitung akumulasi CO2e harian untuk device (menggunakan mg/m³)
     */
    public static function getDailyAccumulation($deviceId, $date = null)
    {
        $date = $date ?? today();
        
        $result = self::where('device_id', $deviceId)
                     ->whereDate('timestamp', $date)
                     ->selectRaw('
                         COUNT(*) as record_count,
                         SUM(co2e_mg_m3) as total_co2e_mg_m3,
                         AVG(co2e_mg_m3) as avg_co2e_mg_m3,
                         MAX(co2e_mg_m3) as max_co2e_mg_m3,
                         MIN(co2e_mg_m3) as min_co2e_mg_m3,
                         MAX(timestamp) as last_reading
                     ')
                     ->first();

        return [
            'device_id' => $deviceId,
            'date' => $date->format('Y-m-d'),
            'record_count' => $result->record_count ?? 0,
            'total_co2e_mg_m3' => round($result->total_co2e_mg_m3 ?? 0, 2),
            'avg_co2e_mg_m3' => round($result->avg_co2e_mg_m3 ?? 0, 2),
            'max_co2e_mg_m3' => round($result->max_co2e_mg_m3 ?? 0, 2),
            'min_co2e_mg_m3' => round($result->min_co2e_mg_m3 ?? 0, 2),
            'total_emissions_kg' => self::convertMgM3ToKg($result->total_co2e_mg_m3 ?? 0),
            'last_reading' => $result->last_reading,
        ];
    }

    /**
     * 🔥 STATIC METHOD: Hitung akumulasi CO2e bulanan untuk device (menggunakan mg/m³)
     */
    public static function getMonthlyAccumulation($deviceId, $month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        $result = self::where('device_id', $deviceId)
                     ->whereMonth('timestamp', $month)
                     ->whereYear('timestamp', $year)
                     ->selectRaw('
                         COUNT(*) as record_count,
                         SUM(co2e_mg_m3) as total_co2e_mg_m3,
                         AVG(co2e_mg_m3) as avg_co2e_mg_m3,
                         MAX(co2e_mg_m3) as max_co2e_mg_m3,
                         MIN(co2e_mg_m3) as min_co2e_mg_m3,
                         MAX(timestamp) as last_reading
                     ')
                     ->first();

        return [
            'device_id' => $deviceId,
            'month' => $month,
            'year' => $year,
            'record_count' => $result->record_count ?? 0,
            'total_co2e_mg_m3' => round($result->total_co2e_mg_m3 ?? 0, 2),
            'avg_co2e_mg_m3' => round($result->avg_co2e_mg_m3 ?? 0, 2),
            'max_co2e_mg_m3' => round($result->max_co2e_mg_m3 ?? 0, 2),
            'min_co2e_mg_m3' => round($result->min_co2e_mg_m3 ?? 0, 2),
            'total_emissions_kg' => self::convertMgM3ToKg($result->total_co2e_mg_m3 ?? 0),
            'last_reading' => $result->last_reading,
        ];
    }

    /**
     * 🔥 STATIC METHOD: Hitung akumulasi CO2e total untuk device (menggunakan mg/m³)
     */
    public static function getTotalAccumulation($deviceId)
    {
        $result = self::where('device_id', $deviceId)
                     ->selectRaw('
                         COUNT(*) as record_count,
                         SUM(co2e_mg_m3) as total_co2e_mg_m3,
                         AVG(co2e_mg_m3) as avg_co2e_mg_m3,
                         MAX(co2e_mg_m3) as max_co2e_mg_m3,
                         MIN(co2e_mg_m3) as min_co2e_mg_m3,
                         MAX(timestamp) as last_reading,
                         MIN(timestamp) as first_reading
                     ')
                     ->first();

        return [
            'device_id' => $deviceId,
            'record_count' => $result->record_count ?? 0,
            'total_co2e_mg_m3' => round($result->total_co2e_mg_m3 ?? 0, 2),
            'avg_co2e_mg_m3' => round($result->avg_co2e_mg_m3 ?? 0, 2),
            'max_co2e_mg_m3' => round($result->max_co2e_mg_m3 ?? 0, 2),
            'min_co2e_mg_m3' => round($result->min_co2e_mg_m3 ?? 0, 2),
            'total_emissions_kg' => self::convertMgM3ToKg($result->total_co2e_mg_m3 ?? 0),
            'first_reading' => $result->first_reading,
            'last_reading' => $result->last_reading,
        ];
    }

    /**
     * 🔥 STATIC METHOD: Konversi mg/m³ ke kg (formula baru sesuai Arduino)
     * Langsung dari mg/m³ ke kg tanpa konversi PPM
     */
    public static function convertMgM3ToKg($mgM3, $volumeM3 = 1)
    {
        // Langkah: Konversi mg/m³ ke kg dengan volume
        // 1 mg = 0.000001 kg
        // Emisi (kg) = Emisi (mg/m³) × Volume (m³) × 0.000001
        // $emisiKg = $mgM3 * $volumeM3 * 0.000001;
        $emisiKg = $mgM3 * $volumeM3 * 0.0005;
        
        return round($emisiKg, 6); // 6 decimal places untuk akurasi
    }


    /**
     * 🔥 SCOPE: Filter data untuk periode tertentu
     */
    public function scopeForPeriod($query, $startDate, $endDate = null)
    {
        $endDate = $endDate ?? $startDate;
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * 🔥 SCOPE: Filter data real-time (5 menit terakhir)
     */
    public function scopeRealTime($query)
    {
        return $query->where('timestamp', '>=', now()->subMinutes(5));
    }
}
