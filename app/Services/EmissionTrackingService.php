<?php

namespace App\Services;

use App\Models\CarbonCredit;
use App\Models\Co2eData;
use App\Models\SensorData;
use App\Models\GpsData;
use App\Services\CarbonCalculationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmissionTrackingService
{
    protected $carbonCalculationService;

    public function __construct(CarbonCalculationService $carbonCalculationService)
    {
        $this->carbonCalculationService = $carbonCalculationService;
    }

    /**
     * Update semua carbon credits dengan data emisi terbaru
     */
    public function updateAllEmissionData()
    {
        $carbonCredits = CarbonCredit::withDevice()->get();
        $updatedCount = 0;

        foreach ($carbonCredits as $carbonCredit) {
            if ($this->updateCarbonCreditEmissions($carbonCredit)) {
                $updatedCount++;
            }
        }

        Log::info("Updated emission data for {$updatedCount} carbon credits");
        return $updatedCount;
    }

    /**
     * Update data emisi untuk carbon credit tertentu
     */
    public function updateCarbonCreditEmissions(CarbonCredit $carbonCredit)
    {
        if (!$carbonCredit->device_id) {
            return false;
        }

        try {
            DB::beginTransaction();

            // Update data emisi real-time
            $this->updateRealTimeEmissions($carbonCredit);
            
            // Update data lokasi
            $this->updateLocationData($carbonCredit);
            
            // Hitung dan update statistik emisi
            $this->updateEmissionStatistics($carbonCredit);
            
            // Lakukan adjustment kuota jika diperlukan
            if ($carbonCredit->auto_adjustment_enabled) {
                $this->performQuotaAdjustment($carbonCredit);
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error updating emissions for carbon credit {$carbonCredit->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update data emisi real-time
     */
    private function updateRealTimeEmissions(CarbonCredit $carbonCredit)
    {
        $latestCo2e = Co2eData::where('device_id', $carbonCredit->device_id)
                             ->latest('timestamp')
                             ->first();

        if ($latestCo2e) {
            $carbonCredit->current_co2e_mg_m3 = $latestCo2e->co2e_mg_m3;
            $carbonCredit->last_sensor_update = $latestCo2e->timestamp;
            
            // Update status sensor berdasarkan waktu update terakhir
            $minutesSinceUpdate = $latestCo2e->timestamp->diffInMinutes(now());
            if ($minutesSinceUpdate <= 5) {
                $carbonCredit->sensor_status = 'active';
            } elseif ($minutesSinceUpdate <= 30) {
                $carbonCredit->sensor_status = 'inactive';
            } else {
                $carbonCredit->sensor_status = 'error';
            }
        }
    }

    /**
     * Update data lokasi terbaru
     */
    private function updateLocationData(CarbonCredit $carbonCredit)
    {
        $latestGps = GpsData::where('device_id', $carbonCredit->device_id)
                           ->latest('timestamp')
                           ->first();

        if ($latestGps) {
            $carbonCredit->last_latitude = $latestGps->latitude;
            $carbonCredit->last_longitude = $latestGps->longitude;
            $carbonCredit->last_speed_kmph = $latestGps->speed_kmph;
        }
    }

    /**
     * Update statistik emisi (harian, bulanan, total)
     */
    private function updateEmissionStatistics(CarbonCredit $carbonCredit)
    {
        // Hitung emisi harian
        $dailyStats = $this->carbonCalculationService->calculateDailyEmissions($carbonCredit->device_id);
        $carbonCredit->daily_emissions_kg = $dailyStats['total_emissions_kg'];

        // Hitung emisi bulanan
        $monthlyStats = $this->carbonCalculationService->calculateMonthlyEmissions($carbonCredit->device_id);
        $carbonCredit->monthly_emissions_kg = $monthlyStats['total_emissions_kg'];

        // Update total emisi (akumulatif)
        $totalEmissions = Co2eData::where('device_id', $carbonCredit->device_id)
                                 ->get()
                                 ->sum(function ($data) {
                                     $this->carbonCalculationService->convertMgM3ToKg($data->co2e_mg_m3);
                                 });
        
        $carbonCredit->total_emissions_kg = $totalEmissions;
    }

    /**
     * Lakukan adjustment kuota berdasarkan emisi
     */
    private function performQuotaAdjustment(CarbonCredit $carbonCredit)
    {
        $adjustment = $this->carbonCalculationService->calculateQuotaAdjustment($carbonCredit);

        if ($adjustment['adjustment_needed']) {
            $oldAmount = $carbonCredit->amount;
            $newAmount = $adjustment['new_amount'];
            $newQuota = $adjustment['new_quota'];
            
            $carbonCredit->amount = $newAmount;
            $carbonCredit->quantity_to_sell = $newQuota;

            Log::info("Quota adjusted for carbon credit {$carbonCredit->id}: {$oldAmount} -> {$newAmount} kg. Reason: {$adjustment['reason']}");

            // Jika kuota habis, ubah status
            if ($carbonCredit->amount <= 0) {
                $carbonCredit->status = 'sold';
                Log::warning("Carbon credit {$carbonCredit->id} marked as sold due to quota depletion from emissions");
            }
        }
    }

    /**
     * Monitor emisi untuk semua device dan kirim alert jika perlu
     */
    public function monitorEmissions()
    {
        $alerts = [];
        $carbonCredits = CarbonCredit::withDevice()->activeSensor()->get();

        foreach ($carbonCredits as $carbonCredit) {
            $alert = $this->checkEmissionAlerts($carbonCredit);
            if ($alert) {
                $alerts[] = $alert;
            }
        }

        if (!empty($alerts)) {
            $this->sendEmissionAlerts($alerts);
        }

        return $alerts;
    }

    /**
     * Check apakah ada alert untuk carbon credit tertentu
     */
    private function checkEmissionAlerts(CarbonCredit $carbonCredit)
    {
        $alerts = [];

        // Alert 1: Emisi harian melebihi threshold
        if ($carbonCredit->daily_emissions_kg > $carbonCredit->emission_threshold_kg) {
            $alerts[] = [
                'type' => 'high_daily_emissions',
                'carbon_credit_id' => $carbonCredit->id,
                'device_id' => $carbonCredit->device_id,
                'message' => "Emisi harian ({$carbonCredit->daily_emissions_kg} kg) melebihi batas ({$carbonCredit->emission_threshold_kg} kg)",
                'severity' => 'warning',
                'current_value' => $carbonCredit->daily_emissions_kg,
                'threshold' => $carbonCredit->emission_threshold_kg
            ];
        }

        // Alert 2: CO2e mg/m³ sangat tinggi
        if ($carbonCredit->current_co2e_mg_m3 > 100) { // Threshold 100 mg/m³
            $alerts[] = [
                'type' => 'high_co2e_mg_m3',
                'carbon_credit_id' => $carbonCredit->id,
                'device_id' => $carbonCredit->device_id,
                'message' => "Tingkat CO2e sangat tinggi: {$carbonCredit->current_co2e_mg_m3} mg/m³",
                'severity' => 'critical',
                'current_value' => $carbonCredit->current_co2e_mg_m3,
                'threshold' => 100
            ];
        }

        // Alert 3: Sensor tidak aktif
        if ($carbonCredit->sensor_status === 'error' || $carbonCredit->sensor_status === 'inactive') {
            $lastUpdate = $carbonCredit->last_sensor_update ? 
                         $carbonCredit->last_sensor_update->diffForHumans() : 
                         'tidak pernah';
            
            $alerts[] = [
                'type' => 'sensor_inactive',
                'carbon_credit_id' => $carbonCredit->id,
                'device_id' => $carbonCredit->device_id,
                'message' => "Sensor tidak aktif. Update terakhir: {$lastUpdate}",
                'severity' => 'error',
                'last_update' => $lastUpdate
            ];
        }

        // Alert 4: Kuota hampir habis
        if ($carbonCredit->quantity_to_sell <= ($carbonCredit->amount * 0.1)) { // 10% dari kuota awal
            $alerts[] = [
                'type' => 'low_quota',
                'carbon_credit_id' => $carbonCredit->id,
                'device_id' => $carbonCredit->device_id,
                'message' => "Kuota karbon hampir habis: {$carbonCredit->quantity_to_sell} kg tersisa",
                'severity' => 'warning',
                'remaining_quota' => $carbonCredit->quantity_to_sell,
                'original_quota' => $carbonCredit->amount
            ];
        }

        return empty($alerts) ? null : [
            'carbon_credit' => $carbonCredit,
            'alerts' => $alerts
        ];
    }

    /**
     * Kirim alert emisi (bisa dikembangkan untuk email, SMS, dll)
     */
    private function sendEmissionAlerts($alerts)
    {
        foreach ($alerts as $alertGroup) {
            $carbonCredit = $alertGroup['carbon_credit'];
            
            foreach ($alertGroup['alerts'] as $alert) {
                Log::channel('emission_alerts')->warning("EMISSION ALERT", [
                    'carbon_credit_id' => $carbonCredit->id,
                    'owner_id' => $carbonCredit->owner_id,
                    'device_id' => $carbonCredit->device_id,
                    'nrkb' => $carbonCredit->nrkb,
                    'alert' => $alert
                ]);

                // TODO: Implementasi notifikasi email/SMS ke pemilik kendaraan
                // $this->sendEmailAlert($carbonCredit->owner, $alert);
            }
        }
    }

    /**
     * Generate laporan emisi harian untuk semua device
     */
    public function generateDailyEmissionReport($date = null)
    {
        $date = $date ?? today();
        $carbonCredits = CarbonCredit::withDevice()->get();
        $report = [
            'date' => $date->format('Y-m-d'),
            'total_devices' => $carbonCredits->count(),
            'active_devices' => 0,
            'total_emissions_kg' => 0,
            'average_emissions_kg' => 0,
            'high_emission_devices' => [],
            'device_details' => []
        ];

        foreach ($carbonCredits as $carbonCredit) {
            $dailyStats = $this->carbonCalculationService->calculateDailyEmissions($carbonCredit->device_id, $date);
            
            if ($dailyStats['record_count'] > 0) {
                $report['active_devices']++;
                $report['total_emissions_kg'] += $dailyStats['total_emissions_kg'];

                $deviceReport = [
                    'carbon_credit_id' => $carbonCredit->id,
                    'device_id' => $carbonCredit->device_id,
                    'nrkb' => $carbonCredit->nrkb,
                    'vehicle_type' => $carbonCredit->vehicle_type,
                    'owner_name' => $carbonCredit->owner->name ?? 'Unknown',
                    'emissions_kg' => $dailyStats['total_emissions_kg'],
                    'average_co2e_mg_m3' => $dailyStats['average_co2e_mg_m3'],
                    'max_co2e_mg_m3' => $dailyStats['max_co2e_mg_m3'],
                    'record_count' => $dailyStats['record_count'],
                    'threshold_exceeded' => $dailyStats['total_emissions_kg'] > $carbonCredit->emission_threshold_kg
                ];

                $report['device_details'][] = $deviceReport;

                // Track high emission devices
                if ($deviceReport['threshold_exceeded']) {
                    $report['high_emission_devices'][] = $deviceReport;
                }
            }
        }

        $report['average_emissions_kg'] = $report['active_devices'] > 0 ? 
                                         $report['total_emissions_kg'] / $report['active_devices'] : 0;

        // Sort devices by emissions (highest first)
        usort($report['device_details'], function ($a, $b) {
            return $b['emissions_kg'] <=> $a['emissions_kg'];
        });

        return $report;
    }

    /**
     * Cleanup data lama untuk menghemat storage
     */
    public function cleanupOldData($daysToKeep = 90)
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedSensor = SensorData::where('timestamp', '<', $cutoffDate)->delete();
        $deletedCo2e = Co2eData::where('timestamp', '<', $cutoffDate)->delete();
        $deletedGps = GpsData::where('timestamp', '<', $cutoffDate)->delete();
        $deletedStatus = \App\Models\StatusLog::where('timestamp', '<', $cutoffDate)->delete();

        Log::info("Cleaned up old data: {$deletedSensor} sensor records, {$deletedCo2e} CO2e records, {$deletedGps} GPS records, {$deletedStatus} status logs");

        return [
            'sensor_data' => $deletedSensor,
            'co2e_data' => $deletedCo2e,
            'gps_data' => $deletedGps,
            'status_logs' => $deletedStatus,
            'total' => $deletedSensor + $deletedCo2e + $deletedGps + $deletedStatus
        ];
    }

    /**
     * Get dashboard statistics untuk monitoring
     */
    public function getDashboardStats()
    {
        $totalDevices = CarbonCredit::withDevice()->count();
        $activeDevices = CarbonCredit::withDevice()->activeSensor()->count();
        $todayEmissions = Co2eData::whereDate('timestamp', today())->count();
        
        // Hitung total emisi hari ini
        $todayTotalEmissions = Co2eData::whereDate('timestamp', today())
                                     ->get()
                                     ->sum(function ($data) {
                                         $this->carbonCalculationService->convertMgM3ToKg($data->co2e_mg_m3);
                                     });

        // Device dengan emisi tertinggi hari ini
        $topEmitters = CarbonCredit::withDevice()
                                  ->where('daily_emissions_kg', '>', 0)
                                  ->orderBy('daily_emissions_kg', 'desc')
                                  ->limit(5)
                                  ->get(['id', 'device_id', 'nrkb', 'vehicle_type', 'daily_emissions_kg']);

        // Alert count
        $alertCount = CarbonCredit::withDevice()
                                 ->where(function ($query) {
                                     $query->where('sensor_status', 'error')
                                           ->orWhere('sensor_status', 'inactive')
                                           ->orWhereRaw('daily_emissions_kg > emission_threshold_kg');
                                 })
                                 ->count();

        return [
            'total_devices' => $totalDevices,
            'active_devices' => $activeDevices,
            'inactive_devices' => $totalDevices - $activeDevices,
            'today_records' => $todayEmissions,
            'today_total_emissions_kg' => $todayTotalEmissions,
            'alert_count' => $alertCount,
            'top_emitters' => $topEmitters,
            'last_updated' => now()->format('Y-m-d H:i:s')
        ];
    }
}
