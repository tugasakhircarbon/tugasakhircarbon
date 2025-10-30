<?php

namespace App\Services;

use App\Models\SensorData;
use App\Models\Co2eData;
use App\Models\GpsData;
use App\Models\StatusLog;
use App\Models\CarbonCredit;
use App\Services\CarbonCalculationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MqttDataService
{
    protected $carbonCalculationService;

    public function __construct(CarbonCalculationService $carbonCalculationService)
    {
        $this->carbonCalculationService = $carbonCalculationService;
    }
    /**
     * Process sensor data dari MQTT
     */
    public function processSensorData(array $data)
    {
        try {
            DB::beginTransaction();

            $deviceId = $data['device_id'] ?? 'unknown';
            
            // 🔥 PERBAIKAN TIMESTAMP - Arduino mengirim millis(), bukan Unix timestamp
            $timestamp = $this->parseTimestamp($data['timestamp'] ?? null);

            // Extract data dari payload MQTT
            $environmentalData = $data['environmental'] ?? [];
            $gasData = $data['gases'] ?? [];
            $particulateData = $data['particulates'] ?? [];

            Log::info("🔥 PROCESSING SENSOR DATA", [
                'device_id' => $deviceId,
                'raw_timestamp' => $data['timestamp'] ?? null,
                'parsed_timestamp' => $timestamp,
                'co_ppm' => $gasData['co_ppm'] ?? null,
                'ch4_ppm' => $gasData['ch4_ppm'] ?? null,
                'n2o_ppm' => $gasData['n2o_ppm'] ?? null
            ]);

            // Simpan sensor data (Arduino tidak mengukur CO2 langsung)
            $sensorData = SensorData::create([
                'device_id' => $deviceId,
                'timestamp' => $timestamp,
                'humidity' => $environmentalData['humidity'] ?? null,
                'temperature_c' => $environmentalData['temperature_c'] ?? null,
                'temperature_f' => $environmentalData['temperature_f'] ?? null,
                'co_ppm' => $gasData['co_ppm'] ?? null,
                'nh3_ppm' => $gasData['nh3_ppm'] ?? null,
                'no2_ppm' => $gasData['no2_ppm'] ?? null,
                'hydrocarbon_ppm' => $gasData['hydrocarbon_ppm'] ?? null,
                // Arduino tidak mengukur CO2 langsung - dihapus
                // 'co2_ppm' => $gasData['co2_ppm'] ?? null,
                'ch4_ppm' => $gasData['ch4_ppm'] ?? null, // Estimasi dari hydrocarbon
                'n2o_ppm' => $gasData['n2o_ppm'] ?? null, // Estimasi dari NO2
                'pm_density' => $particulateData['pm_density'] ?? null,
            ]);

            // 🔥 OTOMATIS HITUNG CO2e DARI DATA SENSOR
            $this->autoCalculateAndStoreCo2e($sensorData);

            // Update carbon credit jika ada
            $this->updateCarbonCreditFromSensor($deviceId, $sensorData);

            DB::commit();

            Log::info("✅ Sensor data berhasil diproses untuk device: {$deviceId} dengan auto CO2e calculation, Timestamp: {$timestamp}");
            return $sensorData;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("❌ Error processing sensor data: " . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process CO2e data dari MQTT
     */
    public function processCo2eData(array $data)
    {
        try {
            DB::beginTransaction();

            $deviceId = $data['device_id'] ?? 'unknown';
            
            // 🔥 PERBAIKAN TIMESTAMP - Arduino mengirim millis(), bukan Unix timestamp
            $timestamp = $this->parseTimestamp($data['timestamp'] ?? null);

            $contributors = $data['contributors'] ?? [];
            $gwpValues = $data['gwp_values'] ?? [];

            Log::info("🔥 PROCESSING CO2e DATA", [
                'device_id' => $deviceId,
                'raw_timestamp' => $data['timestamp'] ?? null,
                'parsed_timestamp' => $timestamp,
                'co2e_mg_m3' => $data['co2e_mg_m3'] ?? 0
            ]);

            // Simpan CO2e data (sesuai schema baru - CO, NH3, NO2)
            $co2eData = Co2eData::create([
                'device_id' => $deviceId,
                'timestamp' => $timestamp,
                'co2e_mg_m3' => $data['co2e_mg_m3'] ?? 0,
                // Arduino menggunakan CO, NH3, NO2 - tidak ada CO2, CH4, N2O langsung
                'co_contribution' => $contributors['co_mg_m3'] ?? null,
                'nh3_contribution' => $contributors['nh3_mg_m3'] ?? null,
                'no2_contribution' => $contributors['no2_mg_m3'] ?? null,
                'gwp_co' => $gwpValues['gwp_co'] ?? null,
                'gwp_nh3' => $gwpValues['gwp_nh3'] ?? null,
                'gwp_no2' => $gwpValues['gwp_no2'] ?? null,
                // Mass concentrations untuk debugging
                'co_mg_m3' => $data['mass_concentrations']['co_mg_m3'] ?? 0,
                'nh3_mg_m3' => $data['mass_concentrations']['nh3_mg_m3'] ?? 0,
                'no2_mg_m3' => $data['mass_concentrations']['no2_mg_m3'] ?? 0,
            ]);

            // Update carbon credit dengan data CO2e terbaru
            $this->updateCarbonCreditFromCo2e($deviceId, $co2eData);

            DB::commit();

            Log::info("✅ CO2e data berhasil diproses untuk device: {$deviceId}, CO2e: {$data['co2e_mg_m3']} mg/m³, Timestamp: {$timestamp}");
            return $co2eData;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("❌ Error processing CO2e data: " . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process GPS data dari MQTT
     */
    public function processGpsData(array $data)
    {
        try {
            $deviceId = $data['device_id'] ?? 'unknown';
            $timestamp = isset($data['timestamp']) 
                ? Carbon::createFromTimestamp($data['timestamp'] / 1000) 
                : now();

            $location = $data['location'] ?? [];
            $datetime = $data['datetime'] ?? [];

            // Skip jika tidak ada koordinat valid
            if (empty($location['latitude']) || empty($location['longitude'])) {
                Log::warning("GPS data tidak valid untuk device: {$deviceId}");
                return null;
            }

            // Simpan GPS data
            $gpsData = GpsData::create([
                'device_id' => $deviceId,
                'timestamp' => $timestamp,
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'speed_kmph' => $location['speed_kmph'] ?? null,
                'date_recorded' => $datetime['date'] ?? null,
                'time_recorded' => $datetime['time'] ?? null,
            ]);

            // Update carbon credit dengan lokasi terbaru
            $this->updateCarbonCreditFromGps($deviceId, $gpsData);

            Log::info("GPS data berhasil diproses untuk device: {$deviceId}, Koordinat: {$location['latitude']}, {$location['longitude']}");
            return $gpsData;

        } catch (\Exception $e) {
            Log::error("Error processing GPS data: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process status log dari MQTT
     */
    public function processStatusLog(array $data)
    {
        try {
            $deviceId = $data['device_id'] ?? 'unknown';
            $timestamp = isset($data['timestamp']) 
                ? Carbon::createFromTimestamp($data['timestamp'] / 1000) 
                : now();

            // Simpan status log
            $statusLog = StatusLog::create([
                'device_id' => $deviceId,
                'timestamp' => $timestamp,
                'status' => $data['status'] ?? 'unknown',
                'ip_address' => $data['ip_address'] ?? null,
            ]);

            // Update status sensor di carbon credit
            $this->updateCarbonCreditStatus($deviceId, $data['status'] ?? 'unknown');

            Log::info("Status log berhasil diproses untuk device: {$deviceId}, Status: {$data['status']}");
            return $statusLog;

        } catch (\Exception $e) {
            Log::error("Error processing status log: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update carbon credit dari sensor data
     */
    private function updateCarbonCreditFromSensor($deviceId, SensorData $sensorData)
    {
        $carbonCredit = CarbonCredit::where('device_id', $deviceId)->first();
        
        if ($carbonCredit) {
            $carbonCredit->last_sensor_update = $sensorData->timestamp;
            $carbonCredit->sensor_status = 'active';
            $carbonCredit->save();
        }
    }

    /**
     * Update carbon credit dari CO2e data dengan akumulasi yang lebih akurat
     */
    private function updateCarbonCreditFromCo2e($deviceId, Co2eData $co2eData)
    {
        $carbonCredit = CarbonCredit::where('device_id', $deviceId)->first();
        
        if ($carbonCredit) {
            Log::info("🔥 MULAI UPDATE CARBON CREDIT untuk device: {$deviceId}", [
                'new_co2e_mg_m3' => $co2eData->co2e_mg_m3,
                'timestamp' => $co2eData->timestamp
            ]);

            // Update CO2e mg/m³ terbaru (untuk display real-time)
            $carbonCredit->current_co2e_mg_m3 = $co2eData->co2e_mg_m3;
            $carbonCredit->last_sensor_update = $co2eData->timestamp;
            
            // 🔥 HITUNG AKUMULASI HARIAN MENGGUNAKAN METHOD HELPER
            $dailyAccumulation = Co2eData::getDailyAccumulation($deviceId);
            $carbonCredit->daily_emissions_kg = $dailyAccumulation['total_emissions_kg'];
            
            Log::info("📊 AKUMULASI HARIAN untuk device {$deviceId}:", [
                'total_co2e_mg_m3' => $dailyAccumulation['total_co2e_mg_m3'],
                'record_count' => $dailyAccumulation['record_count'],
                'avg_co2e_mg_m3' => $dailyAccumulation['avg_co2e_mg_m3'],
                'total_emissions_kg' => $dailyAccumulation['total_emissions_kg']
            ]);
            
            // 🔥 HITUNG AKUMULASI BULANAN MENGGUNAKAN METHOD HELPER
            $monthlyAccumulation = Co2eData::getMonthlyAccumulation($deviceId);
            $carbonCredit->monthly_emissions_kg = $monthlyAccumulation['total_emissions_kg'];
            
            Log::info("📊 AKUMULASI BULANAN untuk device {$deviceId}:", [
                'total_co2e_mg_m3' => $monthlyAccumulation['total_co2e_mg_m3'],
                'record_count' => $monthlyAccumulation['record_count'],
                'total_emissions_kg' => $monthlyAccumulation['total_emissions_kg']
            ]);
            
            // 🔥 HITUNG AKUMULASI TOTAL MENGGUNAKAN METHOD HELPER
            $totalAccumulation = Co2eData::getTotalAccumulation($deviceId);
            $carbonCredit->total_emissions_kg = $totalAccumulation['total_emissions_kg'];
            
            Log::info("📊 AKUMULASI TOTAL untuk device {$deviceId}:", [
                'total_co2e_mg_m3' => $totalAccumulation['total_co2e_mg_m3'],
                'record_count' => $totalAccumulation['record_count'],
                'total_emissions_kg' => $totalAccumulation['total_emissions_kg']
            ]);
            
            // 🎯 SELALU CEK MARKETPLACE LOGIC - tidak bergantung pada threshold
            $this->checkMarketplaceValidity($carbonCredit);
            
            // Auto adjustment kuota jika diaktifkan (untuk logging dan alert saja)
            if ($carbonCredit->auto_adjustment_enabled && 
                $carbonCredit->daily_emissions_kg > ($carbonCredit->emission_threshold_kg ?? 25)) {
                Log::warning("⚠️ THRESHOLD EXCEEDED untuk device {$deviceId}: {$carbonCredit->daily_emissions_kg} kg > {$carbonCredit->emission_threshold_kg} kg");
            }
            
            $carbonCredit->save();
            
            Log::info("✅ CARBON CREDIT BERHASIL DIUPDATE untuk device {$deviceId}:", [
                'current_co2e_mg_m3' => $co2eData->co2e_mg_m3,
                'daily_total_mg_m3' => $dailyAccumulation['total_co2e_mg_m3'],
                'daily_emissions_kg' => $carbonCredit->daily_emissions_kg,
                'monthly_emissions_kg' => $carbonCredit->monthly_emissions_kg,
                'total_emissions_kg' => $carbonCredit->total_emissions_kg,
                'daily_record_count' => $dailyAccumulation['record_count']
            ]);
        } else {
            Log::warning("❌ CARBON CREDIT TIDAK DITEMUKAN untuk device: {$deviceId}");
        }
    }

    /**
     * Konversi PPM ke kg dengan formula yang akurat
     */
    private function convertPpmToKg($ppm, $volumeM3 = 1)
    {
        // Formula: PPM * volume_factor * volume * density_co2
        // Asumsi: 1 PPM CO2e ≈ 0.001 m³ * 1.98 kg/m³ = 0.00198 kg
        return $ppm * 0.001 * 1.98 * $volumeM3;
    }

    /**
     * Update carbon credit dari GPS data
     */
    private function updateCarbonCreditFromGps($deviceId, GpsData $gpsData)
    {
        $carbonCredit = CarbonCredit::where('device_id', $deviceId)->first();
        
        if ($carbonCredit) {
            $carbonCredit->last_latitude = $gpsData->latitude;
            $carbonCredit->last_longitude = $gpsData->longitude;
            $carbonCredit->last_speed_kmph = $gpsData->speed_kmph;
            $carbonCredit->save();
        }
    }

    /**
     * Update status carbon credit
     */
    private function updateCarbonCreditStatus($deviceId, $status)
    {
        $carbonCredit = CarbonCredit::where('device_id', $deviceId)->first();
        
        if ($carbonCredit) {
            // Tentukan status sensor berdasarkan status log
            if (stripos($status, 'online') !== false || 
                stripos($status, 'active') !== false || 
                stripos($status, 'connected') !== false) {
                $carbonCredit->sensor_status = 'active';
            } elseif (stripos($status, 'error') !== false) {
                $carbonCredit->sensor_status = 'error';
            } else {
                $carbonCredit->sensor_status = 'inactive';
            }
            
            $carbonCredit->save();
        }
    }

    /**
     * 🎯 CEK VALIDITAS MARKETPLACE - LOGIKA SEDERHANA
     * Sisa Kuota >= Quantity To Sell → TETAP DI MARKETPLACE
     * Sisa Kuota < Quantity To Sell → HAPUS DARI MARKETPLACE
     */
    private function checkMarketplaceValidity(CarbonCredit $carbonCredit)
    {
        // Hanya cek jika sedang dijual di marketplace
        if ($carbonCredit->status !== 'available' || !$carbonCredit->sale_approved_at) {
            return;
        }

        $totalQuota = $carbonCredit->amount;
        $quantityBeingSold = $carbonCredit->quantity_to_sell;
        $dailyEmissions = $carbonCredit->daily_emissions_kg;
        
        // 🎯 LOGIKA SEDERHANA: Sisa Kuota = Total - Emisi Harian
        $sisaKuota = $totalQuota - $dailyEmissions;
        
        Log::info("🔍 MARKETPLACE VALIDATION untuk device {$carbonCredit->device_id}:", [
            'punya_total' => $totalQuota,
            'jual' => $quantityBeingSold,
            'pakai_harian' => $dailyEmissions,
            'sisa_kuota' => $sisaKuota,
            'formula' => "{$totalQuota}kg - {$dailyEmissions}kg = {$sisaKuota}kg"
        ]);
        
        // Jika sisa kuota tidak cukup untuk yang dijual → INVALID
        if ($sisaKuota < $quantityBeingSold) {
            
            Log::critical("🚨 MARKETPLACE INVALID - Sisa kuota tidak cukup!", [
                'device_id' => $carbonCredit->device_id,
                'nrkb' => $carbonCredit->nrkb,
                'contoh' => "Punya {$totalQuota}kg → jual {$quantityBeingSold}kg → pakai {$dailyEmissions}kg → sisa {$sisaKuota}kg < jual {$quantityBeingSold}kg = INVALID",
                'sisa_kuota' => $sisaKuota,
                'quantity_sold' => $quantityBeingSold,
                'shortfall' => $quantityBeingSold - $sisaKuota,
                'action' => 'HAPUS DARI MARKETPLACE'
            ]);
            
            // Hapus dari marketplace - kembali ke pending_sale
            $carbonCredit->status = 'pending_sale';
            $carbonCredit->quantity_to_sell = 0;
            $carbonCredit->sale_approved_at = null;
            
            Log::critical("✅ REMOVED FROM MARKETPLACE - device {$carbonCredit->device_id}", [
                'reason' => 'Sisa kuota tidak mencukupi untuk penjualan yang diajukan',
                'old_status' => 'available',
                'new_status' => 'pending_sale',
                'message' => 'User perlu mengajukan ulang dengan kuota yang sesuai kondisi terkini'
            ]);
            
        } else {
            // Sisa kuota masih cukup → VALID, tetap di marketplace
            Log::info("✅ MARKETPLACE MASIH VALID untuk device {$carbonCredit->device_id}", [
                'sisa_kuota' => $sisaKuota,
                'quantity_sold' => $quantityBeingSold,
                'surplus' => $sisaKuota - $quantityBeingSold,
                'status' => 'Tetap di marketplace'
            ]);
        }
        
        // Jika kuota habis total
        if ($sisaKuota <= 0) {
            $carbonCredit->quantity_to_sell = 0;
            $carbonCredit->amount = 0;
            $carbonCredit->status = 'exhausted';
            $carbonCredit->sale_approved_at = null;
            
            Log::critical("🚨 KUOTA HABIS TOTAL - REMOVED FROM MARKETPLACE!", [
                'device_id' => $carbonCredit->device_id,
                'action' => 'Status changed to exhausted'
            ]);
        }
    }

    /**
     * Adjust kuota karbon berdasarkan emisi dengan logika marketplace yang benar
     * 🔥 LOGIKA BENAR: Kuota berkurang langsung sesuai emisi yang digunakan
     */
    private function adjustCarbonQuota(CarbonCredit $carbonCredit)
    {
        $oldAmount = $carbonCredit->amount;
        $quantityBeingSold = $carbonCredit->quantity_to_sell;
        $dailyEmissions = $carbonCredit->daily_emissions_kg;
        
        // 🎯 KONSEP BENAR: Kuota berkurang langsung sesuai emisi yang digunakan
        // Tidak ada "pengurangan tambahan" - emisi langsung mengurangi kuota
        $newTotalQuota = max(0, $oldAmount - $dailyEmissions);
        $carbonCredit->amount = $newTotalQuota;
        
        Log::warning("🔥 KUOTA BERKURANG KARENA EMISI untuk device {$carbonCredit->device_id}:", [
            'old_total_quota' => $oldAmount,
            'daily_emissions_kg' => $dailyEmissions,
            'new_total_quota' => $newTotalQuota,
            'quantity_being_sold' => $quantityBeingSold,
            'formula' => "{$oldAmount}kg - {$dailyEmissions}kg = {$newTotalQuota}kg"
        ]);
        
        // 🎯 LOGIKA UTAMA: Cek apakah sisa kuota masih cukup untuk yang dijual
        if ($carbonCredit->status === 'available' && $carbonCredit->sale_approved_at) {
            
            Log::info("🔍 MARKETPLACE VALIDATION untuk device {$carbonCredit->device_id}:", [
                'total_quota_now' => $newTotalQuota,
                'quantity_being_sold' => $quantityBeingSold,
                'is_valid' => $newTotalQuota >= $quantityBeingSold
            ]);
            
            // Jika sisa kuota tidak cukup untuk yang dijual → INVALID
            if ($newTotalQuota < $quantityBeingSold) {
                
                Log::critical("🚨 MARKETPLACE INVALID - Sisa kuota tidak cukup!", [
                    'device_id' => $carbonCredit->device_id,
                    'nrkb' => $carbonCredit->nrkb,
                    'contoh' => 'Punya 100kg → jual 50kg → pakai 60kg → sisa 40kg < jual 50kg = INVALID',
                    'total_quota' => $newTotalQuota,
                    'quantity_sold' => $quantityBeingSold,
                    'shortfall' => $quantityBeingSold - $newTotalQuota,
                    'action' => 'HAPUS DARI MARKETPLACE'
                ]);
                
                // Hapus dari marketplace - kembali ke pending_sale
                $carbonCredit->status = 'pending_sale';
                $carbonCredit->quantity_to_sell = 0; // Reset quantity to sell
                $carbonCredit->sale_approved_at = null;
                
                Log::critical("✅ REMOVED FROM MARKETPLACE - device {$carbonCredit->device_id}", [
                    'reason' => 'Sisa kuota tidak mencukupi untuk penjualan yang diajukan',
                    'old_status' => 'available',
                    'new_status' => 'pending_sale',
                    'message' => 'User perlu mengajukan ulang dengan kuota yang sesuai kondisi terkini'
                ]);
                
            } else {
                // Sisa kuota masih cukup → VALID, tetap di marketplace
                Log::info("✅ MARKETPLACE MASIH VALID untuk device {$carbonCredit->device_id}", [
                    'total_quota' => $newTotalQuota,
                    'quantity_sold' => $quantityBeingSold,
                    'surplus' => $newTotalQuota - $quantityBeingSold,
                    'status' => 'Tetap di marketplace'
                ]);
            }
            
        } else {
            // Tidak di marketplace, update quantity_to_sell sesuai sisa kuota
            $carbonCredit->quantity_to_sell = $newTotalQuota;
            
            Log::info("📊 QUOTA UPDATED (not in marketplace) untuk device {$carbonCredit->device_id}:", [
                'new_quantity_to_sell' => $carbonCredit->quantity_to_sell,
                'new_total_quota' => $newTotalQuota
            ]);
        }
        
        // Jika kuota habis total
        if ($newTotalQuota <= 0) {
            $carbonCredit->quantity_to_sell = 0;
            $carbonCredit->amount = 0;
            $carbonCredit->status = 'exhausted';
            
            if ($carbonCredit->sale_approved_at) {
                $carbonCredit->sale_approved_at = null;
                Log::critical("🚨 KUOTA HABIS TOTAL - REMOVED FROM MARKETPLACE!", [
                    'device_id' => $carbonCredit->device_id,
                    'action' => 'Status changed to exhausted'
                ]);
            }
        }
    }

    /**
     * Get statistik emisi untuk device
     */
    public function getEmissionStats($deviceId, $period = 'daily')
    {
        $query = Co2eData::where('device_id', $deviceId);
        
        switch ($period) {
            case 'daily':
                $query->whereDate('timestamp', today());
                break;
            case 'weekly':
                $query->whereBetween('timestamp', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereMonth('timestamp', now()->month)
                      ->whereYear('timestamp', now()->year);
                break;
        }
        
        return [
        'total_records' => $query->count(),
        'avg_co2e_mg_m3' => $query->avg('co2e_mg_m3'),
        'max_co2e_mg_m3' => $query->max('co2e_mg_m3'),
        'min_co2e_mg_m3' => $query->min('co2e_mg_m3'),
        'total_emissions_kg' => $query->get()->sum(function ($data) {
            return Co2eData::convertMgM3ToKg($data->co2e_mg_m3);}),
        ];
    }

    /**
     * 🔥 OTOMATIS HITUNG CO2e DARI DATA SENSOR
     * Method ini akan menghitung CO2 equivalent dari data sensor yang baru masuk
     */
    private function autoCalculateAndStoreCo2e(SensorData $sensorData)
    {
        try {
            Log::info("🔥 AUTO CALCULATION STARTED untuk device: {$sensorData->device_id}", [
                // Arduino tidak mengukur CO2 langsung
                'co_ppm' => $sensorData->co_ppm,
                'ch4_ppm' => $sensorData->ch4_ppm,
                'n2o_ppm' => $sensorData->n2o_ppm
            ]);

            // Cek apakah ada data gas yang valid untuk perhitungan
            if (!$this->hasValidGasData($sensorData)) {
                Log::warning("❌ Tidak ada data gas yang valid untuk perhitungan CO2e pada device: {$sensorData->device_id}", [
                    // Arduino mengukur CO, NH3, NO2
                    'co_ppm' => $sensorData->co_ppm,
                    'nh3_ppm' => $sensorData->nh3_ppm,
                    'no2_ppm' => $sensorData->no2_ppm
                ]);
                return null;
            }

            Log::info("✅ Data gas valid, memulai perhitungan CO2e...");

            // Hitung CO2e menggunakan CarbonCalculationService
            $co2eCalculation = $this->carbonCalculationService->calculateCo2Equivalent($sensorData);

            Log::info("✅ Perhitungan CO2e selesai", [
                'co2e_mg_m3' => $co2eCalculation['co2e_mg_m3'],
                'contributors' => $co2eCalculation['contributors']
            ]);

            // Simpan hasil perhitungan ke database (sesuai schema baru)
            $co2eData = Co2eData::create([
                'device_id' => $co2eCalculation['device_id'],
                'timestamp' => $this->parseTimestamp($co2eCalculation['timestamp']),
                'co2e_mg_m3' => $co2eCalculation['co2e_mg_m3'],
                // Arduino menggunakan CO, NH3, NO2 - tidak ada CO2, CH4, N2O langsung
                'co_contribution' => $co2eCalculation['contributors']['co_contribution'],
                'nh3_contribution' => $co2eCalculation['contributors']['nh3_contribution'],
                'no2_contribution' => $co2eCalculation['contributors']['no2_contribution'],
                'gwp_co' => $co2eCalculation['gwp_values']['co'],
                'gwp_nh3' => $co2eCalculation['gwp_values']['nh3'],
                'gwp_no2' => $co2eCalculation['gwp_values']['no2'],
                // Mass concentrations untuk debugging
                'co_mg_m3' => $co2eCalculation['mass_concentrations']['co_mg_m3'],
                'nh3_mg_m3' => $co2eCalculation['mass_concentrations']['nh3_mg_m3'],
                'no2_mg_m3' => $co2eCalculation['mass_concentrations']['no2_mg_m3'],
            ]);

            Log::info("✅ CO2e data berhasil disimpan ke database dengan ID: {$co2eData->id}");

            // Update carbon credit dengan data CO2e yang baru dihitung
            $this->updateCarbonCreditFromCo2e($sensorData->device_id, $co2eData);

            Log::info("🎉 CO2e berhasil dihitung otomatis untuk device {$sensorData->device_id}: {$co2eCalculation['co2e_mg_m3']} mg/m³", [
                'device_id' => $sensorData->device_id,
                'co2e_mg_m3' => $co2eCalculation['co2e_mg_m3'],
                'contributors' => $co2eCalculation['contributors'],
                'calculation_method' => 'auto_from_sensor_data',
                'database_id' => $co2eData->id
            ]);

            return $co2eData;

        } catch (\Exception $e) {
            Log::error("❌ CRITICAL ERROR dalam auto calculation CO2e untuk device {$sensorData->device_id}: " . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Cek apakah sensor data memiliki data gas yang valid untuk perhitungan CO2e
     * Arduino mengukur CO, NH3, NO2
     */
    private function hasValidGasData(SensorData $sensorData)
    {
        // Minimal harus ada salah satu dari gas utama (Arduino mengukur CO, NH3, NO2)
        return ($sensorData->co_ppm !== null && $sensorData->co_ppm > 0) ||
               ($sensorData->nh3_ppm !== null && $sensorData->nh3_ppm > 0) ||
               ($sensorData->no2_ppm !== null && $sensorData->no2_ppm > 0);
    }

    /**
     * Get data terbaru untuk device
     */
    public function getLatestData($deviceId)
    {
        return [
            'sensor_data' => SensorData::where('device_id', $deviceId)->latest('timestamp')->first(),
            'co2e_data' => Co2eData::where('device_id', $deviceId)->latest('timestamp')->first(),
            'gps_data' => GpsData::where('device_id', $deviceId)->latest('timestamp')->first(),
            'status_log' => StatusLog::where('device_id', $deviceId)->latest('timestamp')->first(),
            'is_online' => StatusLog::isDeviceOnline($deviceId),
        ];
    }

    /**
     * 🔥 PARSE TIMESTAMP - Handle Arduino millis() dan Unix timestamp
     */
    private function parseTimestamp($rawTimestamp)
    {
        if (empty($rawTimestamp)) {
            return now();
        }

        // Convert to integer
        $timestamp = (int) $rawTimestamp;

        // Arduino millis() biasanya dalam range 0 - beberapa juta (detik sejak boot)
        // Unix timestamp biasanya > 1 miliar (detik sejak 1970)
        
        if ($timestamp < 1000000000) {
            // Ini kemungkinan Arduino millis() dalam milidetik
            // Gunakan waktu sekarang karena millis() tidak memberikan waktu absolut
            Log::info("🔥 TIMESTAMP DETECTED: Arduino millis() = {$timestamp}ms, using current time");
            return now();
        } elseif ($timestamp > 1000000000000) {
            // Ini Unix timestamp dalam milidetik
            Log::info("🔥 TIMESTAMP DETECTED: Unix timestamp in milliseconds = {$timestamp}");
            return Carbon::createFromTimestamp($timestamp / 1000);
        } else {
            // Ini Unix timestamp dalam detik
            Log::info("🔥 TIMESTAMP DETECTED: Unix timestamp in seconds = {$timestamp}");
            return Carbon::createFromTimestamp($timestamp);
        }
    }
}
