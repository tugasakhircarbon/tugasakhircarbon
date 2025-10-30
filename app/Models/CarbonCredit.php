<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarbonCredit extends Model
{
    use HasFactory;

    protected $attributes = [
        'auto_adjustment_enabled' => true,
        'emission_threshold_kg' => 100, // Default threshold, adjust as needed
    ];

    protected $fillable = [
        'owner_id',
        'nomor_kartu_keluarga',
        'pemilik_kendaraan',
        'nik_e_ktp',
        'nrkb',
        'nomor_rangka_5digit',
        'vehicle_type',
        'amount',
        'price_per_unit',
        'status',
        'sale_price_per_unit',
        'quantity_to_sell',
        'sale_requested_at',
        'sale_approved_at',
        // Kolom baru untuk MQTT integration
        'device_id',
        'current_co2e_mg_m3',
        'total_emissions_kg',
        'daily_emissions_kg',
        'monthly_emissions_kg',
        'last_latitude',
        'last_longitude',
        'last_speed_kmph',
        'last_sensor_update',
        'sensor_status',
        'auto_adjustment_enabled',
        'emission_threshold_kg',
    ];

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function transactionDetails()
    {
        return $this->hasMany(\App\Models\TransactionDetail::class, 'carbon_credit_id');
    }

    /**
     * Get the available amount of carbon credit.
     * Note: quantity_to_sell is the actual available quota for sale,
     * while amount is the total quota owned.
     * The marketplace and transaction logic rely on quantity_to_sell for availability.
     */
    public function getAvailableAmountAttribute()
    {
        return $this->quantity_to_sell;
    }

    /**
     * Get the total value of the carbon credit (amount * price_per_unit)
     */
    public function getTotalValueAttribute()
    {
        if ($this->amount && $this->price_per_unit) {
            return $this->amount * $this->price_per_unit;
        }
        return 0;
    }

    /**
     * Get the effective quota after deducting daily emissions.
     */
    public function getEffectiveQuotaAttribute()
    {
        return max(0, ($this->amount ?? 0) - ($this->daily_emissions_kg ?? 0));
    }

    /**
     * Relasi ke SensorData berdasarkan device_id
     */
    public function sensorData()
    {
        return $this->hasMany(SensorData::class, 'device_id', 'device_id');
    }

    /**
     * Relasi ke Co2eData berdasarkan device_id
     */
    public function co2eData()
    {
        return $this->hasMany(Co2eData::class, 'device_id', 'device_id');
    }

    /**
     * Relasi ke GpsData berdasarkan device_id
     */
    public function gpsData()
    {
        return $this->hasMany(GpsData::class, 'device_id', 'device_id');
    }

    /**
     * Relasi ke StatusLog berdasarkan device_id
     */
    public function statusLogs()
    {
        return $this->hasMany(StatusLog::class, 'device_id', 'device_id');
    }

    /**
     * Get sensor data terbaru
     */
    public function getLatestSensorDataAttribute()
    {
        return $this->sensorData()->latest('timestamp')->first();
    }

    /**
     * Get CO2e data terbaru
     */
    public function getLatestCo2eDataAttribute()
    {
        return $this->co2eData()->latest('timestamp')->first();
    }

    /**
     * Get GPS data terbaru
     */
    public function getLatestGpsDataAttribute()
    {
        return $this->gpsData()->latest('timestamp')->first();
    }

    /**
     * Check apakah device sedang online
     */
    public function getIsDeviceOnlineAttribute()
    {
        return StatusLog::isDeviceOnline($this->device_id);
    }

    /**
     * Get status device terakhir
     */
    public function getDeviceStatusAttribute()
    {
        return StatusLog::getLastStatus($this->device_id);
    }

    /**
     * Hitung rata-rata emisi harian
     */
    public function getAverageDailyEmissionsAttribute()
    {
        return $this->co2eData()
                    ->selectRaw('DATE(timestamp) as date, AVG(co2e_mg_m3) as avg_co2e')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->limit(30)
                    ->get()
                    ->avg('avg_co2e') ?? 0;
    }

    /**
     * Scope untuk carbon credits yang memiliki device_id
     */
    public function scopeWithDevice($query)
    {
        return $query->whereNotNull('device_id');
    }

    /**
     * Scope untuk carbon credits dengan sensor aktif
     */
    public function scopeActiveSensor($query)
    {
        return $query->where('sensor_status', 'active');
    }

    /**
     * Update data emisi dari sensor terbaru
     */
    public function updateEmissionData()
    {
        if (!$this->device_id) {
            return false;
        }

        $latestCo2e = $this->co2eData()->latest('timestamp')->first();
        $latestGps = $this->gpsData()->latest('timestamp')->first();

        if ($latestCo2e) {
            $this->current_co2e_mg_m3 = $latestCo2e->co2e_mg_m3;
            $this->last_sensor_update = $latestCo2e->timestamp;

            // Hitung emisi harian menggunakan convertMgM3ToKg
            $dailyEmissions = $this->co2eData()
                                   ->whereDate('timestamp', today())
                                   ->sum('co2e_mg_m3');
            $this->daily_emissions_kg = \App\Models\Co2eData::convertMgM3ToKg($dailyEmissions);

            // Auto-adjust quantity_to_sell if enabled
            if ($this->auto_adjustment_enabled) {
                $this->quantity_to_sell = $this->effective_quota;
            }

            // Hitung emisi bulanan
            $monthlyEmissions = $this->co2eData()
                                     ->whereMonth('timestamp', now()->month)
                                     ->whereYear('timestamp', now()->year)
                                     ->sum('co2e_mg_m3');
            $this->monthly_emissions_kg = \App\Models\Co2eData::convertMgM3ToKg($monthlyEmissions);

            $this->total_emissions_kg += $latestCo2e->emission_in_kg;
        }

        if ($latestGps) {
            $this->last_latitude = $latestGps->latitude;
            $this->last_longitude = $latestGps->longitude;
            $this->last_speed_kmph = $latestGps->speed_kmph;
        }

        // Update status sensor
        $this->sensor_status = $this->is_device_online ? 'active' : 'inactive';

        $this->save();

        return true;
    }
}
