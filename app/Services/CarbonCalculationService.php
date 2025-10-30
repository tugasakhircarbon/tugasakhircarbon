<?php

namespace App\Services;

use App\Models\Co2eData;
use App\Models\SensorData;
use App\Models\CarbonCredit;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CarbonCalculationService
{
    // Global Warming Potential (GWP) values untuk 100 tahun (sesuai dengan Arduino terbaru)
    const GWP_VALUES = [
        'co' => 2.0,     // Carbon Monoxide (sesuai Arduino)
        'nh3' => 3.0,    // Ammonia (sesuai Arduino)
        'no2' => 1.0,    // Nitrogen Dioxide (sesuai Arduino)
    ];

    // Molecular Weights (g/mol) - sesuai dengan Arduino
    const MOLECULAR_WEIGHTS = [
        'co' => 28.01,   // Carbon Monoxide
        'nh3' => 17.03,  // Ammonia
        'no2' => 46.01,  // Nitrogen Dioxide
    ];

    // Molar volume of a gas at standard conditions (25°C, 1 atm) in L/mol
    const MOLAR_VOLUME = 24.45;

    /**
     * Hitung CO2 equivalent dari data sensor (sesuai dengan Arduino terbaru - CO, NH3, NO2)
     */
    public function calculateCo2Equivalent(SensorData $sensorData)
    {
        try {
            // Arduino mengukur CO, NH3, dan NO2 (bukan CH4 dan N2O)
            $coPpm = $sensorData->co_ppm ?? 0;
            $nh3Ppm = $sensorData->nh3_ppm ?? 0;
            $no2Ppm = $sensorData->no2_ppm ?? 0;

            Log::info("🔥 CALCULATING CO2e dari CO, NH3, NO2 (sesuai Arduino terbaru)", [
                'co_ppm' => $coPpm,
                'nh3_ppm' => $nh3Ppm,
                'no2_ppm' => $no2Ppm,
                'gwp_values' => self::GWP_VALUES
            ]);

            // Hitung mass concentration (mg/m³) untuk setiap gas (sesuai formula Arduino)
            $coMassConcentration = $coPpm * (self::MOLECULAR_WEIGHTS['co'] / self::MOLAR_VOLUME);
            $nh3MassConcentration = $nh3Ppm * (self::MOLECULAR_WEIGHTS['nh3'] / self::MOLAR_VOLUME);
            $no2MassConcentration = $no2Ppm * (self::MOLECULAR_WEIGHTS['no2'] / self::MOLAR_VOLUME);

            // Hitung kontribusi CO2e untuk setiap gas (mg/m³)
            $coContribution = $coMassConcentration * self::GWP_VALUES['co'];
            $nh3Contribution = $nh3MassConcentration * self::GWP_VALUES['nh3'];
            $no2Contribution = $no2MassConcentration * self::GWP_VALUES['no2'];

            // Total CO2e dalam mg/m³ (sesuai formula Arduino)
            $totalCo2eMgPerM3 = $coContribution + $nh3Contribution + $no2Contribution;

            // Konversi mg/m³ ke PPM untuk kompatibilitas dengan sistem yang ada
            // Menggunakan massa molar CO2 (44.01 g/mol) sebagai referensi
            $co2ePpm = $totalCo2eMgPerM3 / (44.01 / self::MOLAR_VOLUME);

            Log::info("✅ CO2e CALCULATION RESULT (Arduino Formula)", [
                'co_mass_mg_m3' => $coMassConcentration,
                'nh3_mass_mg_m3' => $nh3MassConcentration,
                'no2_mass_mg_m3' => $no2MassConcentration,
                'co_contribution_mg_m3' => $coContribution,
                'nh3_contribution_mg_m3' => $nh3Contribution,
                'no2_contribution_mg_m3' => $no2Contribution,
                'total_co2e_mg_m3' => $totalCo2eMgPerM3,
            ]);

            return [
                'co2e_mg_m3' => $totalCo2eMgPerM3,
                'contributors' => [
                    'co_contribution' => $coContribution,
                    'nh3_contribution' => $nh3Contribution,
                    'no2_contribution' => $no2Contribution,
                    'ch4_contribution' => null, // Tidak digunakan lagi
                    'n2o_contribution' => null, // Tidak digunakan lagi
                ],
                'mass_concentrations' => [
                    'co_mg_m3' => $coMassConcentration,
                    'nh3_mg_m3' => $nh3MassConcentration,
                    'no2_mg_m3' => $no2MassConcentration,
                ],
                'gwp_values' => self::GWP_VALUES,
                'molecular_weights' => self::MOLECULAR_WEIGHTS,
                'device_id' => $sensorData->device_id,
                'timestamp' => $sensorData->timestamp->timestamp * 1000, // Convert to milliseconds
            ];

        } catch (\Exception $e) {
            Log::error("❌ Error calculating CO2 equivalent: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Konversi mg/m³ ke kilogram menggunakan rumus yang sama dengan Co2eData
     */
    public function convertMgM3ToKg($mgM3, $volumeM3 = 1)
    {
        // Gunakan method yang sama dari Co2eData untuk konsistensi
        return Co2eData::convertMgM3ToKg($mgM3, $volumeM3);
    }

    /**
     * Hitung emisi berdasarkan kecepatan dan jarak
     */
    public function calculateEmissionsBySpeed($speed_kmph, $distance_km, $vehicleType = 'car')
    {
        // Faktor emisi berdasarkan jenis kendaraan (kg CO2/km)
        $emissionFactors = [
            'car' => 0.12,        // 120g CO2/km untuk mobil rata-rata
            'motorcycle' => 0.08, // 80g CO2/km untuk motor rata-rata
        ];

        $factor = $emissionFactors[$vehicleType] ?? $emissionFactors['car'];
        
        // Faktor koreksi berdasarkan kecepatan (emisi lebih tinggi pada kecepatan tinggi)
        $speedFactor = 1.0;
        if ($speed_kmph > 80) {
            $speedFactor = 1.3; // 30% lebih tinggi untuk kecepatan > 80 km/h
        } elseif ($speed_kmph > 60) {
            $speedFactor = 1.1; // 10% lebih tinggi untuk kecepatan > 60 km/h
        } elseif ($speed_kmph < 20) {
            $speedFactor = 1.2; // 20% lebih tinggi untuk kecepatan rendah (macet)
        }

        return $distance_km * $factor * $speedFactor;
    }

    /**
     * Hitung total emisi harian untuk device
     */
    public function calculateDailyEmissions($deviceId, $date = null)
    {
        $date = $date ?? today();
        
        $co2eData = Co2eData::where('device_id', $deviceId)
                           ->whereDate('timestamp', $date)
                           ->get();

        $totalEmissionsKg = 0;
        $recordCount = $co2eData->count();

        foreach ($co2eData as $data) {
            $totalEmissionsKg += $this->convertMgM3ToKg($data->co2e_mg_m3);
        }

        return [
            'device_id' => $deviceId,
            'date' => $date->format('Y-m-d'),
            'total_emissions_kg' => $totalEmissionsKg,
            'average_co2e_mg_m3' => $recordCount > 0 ? $co2eData->avg('co2e_mg_m3') : 0,
            'max_co2e_mg_m3' => $recordCount > 0 ? $co2eData->max('co2e_mg_m3') : 0,
            'record_count' => $recordCount,
        ];
    }

    /**
     * Hitung emisi bulanan untuk device
     */
    public function calculateMonthlyEmissions($deviceId, $month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        $co2eData = Co2eData::where('device_id', $deviceId)
                           ->whereMonth('timestamp', $month)
                           ->whereYear('timestamp', $year)
                           ->get();

        $totalEmissionsKg = 0;
        $recordCount = $co2eData->count();

        foreach ($co2eData as $data) {
            $totalEmissionsKg += $this->convertMgM3ToKg($data->co2e_mg_m3);
        }

        // Hitung emisi harian rata-rata
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $avgDailyEmissions = $totalEmissionsKg / $daysInMonth;

        return [
            'device_id' => $deviceId,
            'month' => $month,
            'year' => $year,
            'total_emissions_kg' => $totalEmissionsKg,
            'average_daily_emissions_kg' => $avgDailyEmissions,
            'average_co2e_mg_m3' => $recordCount > 0 ? $co2eData->avg('co2e_mg_m3') : 0,
            'max_co2e_mg_m3' => $recordCount > 0 ? $co2eData->max('co2e_mg_m3') : 0,
            'record_count' => $recordCount,
        ];
    }

    /**
     * Hitung pengurangan kuota karbon berdasarkan emisi
     */
    public function calculateQuotaAdjustment(CarbonCredit $carbonCredit)
    {
        if (!$carbonCredit->device_id || !$carbonCredit->auto_adjustment_enabled) {
            return [
                'adjustment_needed' => false,
                'reason' => 'Auto adjustment tidak diaktifkan atau device_id tidak ada'
            ];
        }

        $dailyEmissions = $this->calculateDailyEmissions($carbonCredit->device_id);
        $threshold = $carbonCredit->emission_threshold_kg;

        if ($dailyEmissions['total_emissions_kg'] <= $threshold) {
            return [
                'adjustment_needed' => false,
                'daily_emissions' => $dailyEmissions['total_emissions_kg'],
                'threshold' => $threshold,
                'reason' => 'Emisi masih dalam batas normal'
            ];
        }

        // Hitung pengurangan kuota
        $excessEmissions = $dailyEmissions['total_emissions_kg'] - $threshold;
        $quotaReduction = $excessEmissions * 0.1; // 10% dari emisi berlebih

        // Pastikan tidak mengurangi lebih dari kuota yang tersedia
        $maxReduction = min($quotaReduction, $carbonCredit->quantity_to_sell);

        return [
            'adjustment_needed' => true,
            'daily_emissions' => $dailyEmissions['total_emissions_kg'],
            'threshold' => $threshold,
            'excess_emissions' => $excessEmissions,
            'quota_reduction' => $maxReduction,
            'new_quota' => $carbonCredit->quantity_to_sell - $maxReduction,
            'reason' => "Emisi melebihi batas: {$excessEmissions} kg"
        ];
    }

/**
 * Generate laporan emisi untuk periode tertentu
 */
public function generateEmissionReport($deviceId, $startDate, $endDate)
{
    $co2eData = Co2eData::where('device_id', $deviceId)
                       ->whereBetween('timestamp', [$startDate, $endDate])
                       ->orderBy('timestamp')
                       ->get();

    if ($co2eData->isEmpty()) {
        return [
            'device_id' => $deviceId,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_emissions_kg' => 0,
                'average_co2e_mg_m3' => 0,
                'max_co2e_mg_m3' => 0,
                'min_co2e_mg_m3' => 0,
                'record_count' => 0
            ],
            'daily_breakdown' => [],
            'message' => 'Tidak ada data emisi untuk periode ini'
        ];
    }

    // Hitung total emisi
    $totalEmissionsKg = 0;
    $dailyBreakdown = [];

    foreach ($co2eData as $data) {
        $emissionKg = $this->convertMgM3ToKg($data->co2e_mg_m3);
        $totalEmissionsKg += $emissionKg;

        $date = $data->timestamp->format('Y-m-d');
        if (!isset($dailyBreakdown[$date])) {
            $dailyBreakdown[$date] = [
                'date' => $date,
                'total_emissions_kg' => 0,
                'average_co2e_mg_m3' => 0,
                'max_co2e_mg_m3' => 0,
                'record_count' => 0,
                'records' => []
            ];
        }

        $dailyBreakdown[$date]['total_emissions_kg'] += $emissionKg;
        $dailyBreakdown[$date]['max_co2e_mg_m3'] = max($dailyBreakdown[$date]['max_co2e_mg_m3'], $data->co2e_mg_m3);
        $dailyBreakdown[$date]['record_count']++;
        $dailyBreakdown[$date]['records'][] = $data->co2e_mg_m3;
    }

    // Hitung rata-rata untuk setiap hari
    foreach ($dailyBreakdown as &$day) {
        $day['average_co2e_mg_m3'] = array_sum($day['records']) / count($day['records']);
        unset($day['records']); // Hapus detail records untuk menghemat memory
    }

    return [
        'device_id' => $deviceId,
        'period' => [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d')
        ],
        'summary' => [
            'total_emissions_kg' => $totalEmissionsKg,
            'average_co2e_mg_m3' => $co2eData->avg('co2e_mg_m3'),
            'max_co2e_mg_m3' => $co2eData->max('co2e_mg_m3'),
            'min_co2e_mg_m3' => $co2eData->min('co2e_mg_m3'),
            'record_count' => $co2eData->count()
        ],
        'daily_breakdown' => array_values($dailyBreakdown)
    ];
}


/**
 * Prediksi emisi berdasarkan trend historis
 */
public function predictEmissions($deviceId, $days = 7)
{
    // Ambil data 30 hari terakhir untuk analisis trend
    $historicalData = Co2eData::where('device_id', $deviceId)
                             ->where('timestamp', '>=', now()->subDays(30))
                             ->selectRaw('DATE(timestamp) as date, AVG(co2e_mg_m3) as avg_co2e')
                             ->groupBy('date')
                             ->orderBy('date')
                             ->get();

    if ($historicalData->count() < 7) {
        return [
            'device_id' => $deviceId,
            'prediction_days' => $days,
            'predictions' => [],
            'message' => 'Data historis tidak cukup untuk prediksi (minimal 7 hari)'
        ];
    }

    // Hitung trend sederhana (linear regression)
    $values = $historicalData->pluck('avg_co2e')->toArray();
    $n = count($values);
    
    // Hitung slope (kemiringan trend)
    $sumX = array_sum(range(1, $n));
    $sumY = array_sum($values);
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $values[$i];
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    
    // Generate prediksi
    $predictions = [];
    $lastValue = end($values);
    
    for ($i = 1; $i <= $days; $i++) {
        $predictedValue = $lastValue + ($slope * $i);
        $predictedEmissionKg = $this->convertMgM3ToKg($predictedValue);
        
        $predictions[] = [
            'date' => now()->addDays($i)->format('Y-m-d'),
            'predicted_co2e_mg_m3' => max(0, $predictedValue), // Tidak boleh negatif
            'predicted_emissions_kg' => max(0, $predictedEmissionKg),
            'confidence' => $this->calculatePredictionConfidence($historicalData, $i)
        ];
    }

    return [
        'device_id' => $deviceId,
        'prediction_days' => $days,
        'trend_slope' => $slope,
        'trend_direction' => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable'),
        'predictions' => $predictions,
        'historical_average' => array_sum($values) / $n
    ];
}


    /**
     * Hitung confidence level untuk prediksi
     */
    private function calculatePredictionConfidence($historicalData, $daysAhead)
    {
        // Confidence menurun seiring dengan jarak prediksi
        $baseConfidence = 0.8; // 80% untuk hari pertama
        $decayRate = 0.1; // Turun 10% per hari
        
        $confidence = $baseConfidence - ($daysAhead * $decayRate);
        return max(0.3, $confidence); // Minimum 30% confidence
    }
}
