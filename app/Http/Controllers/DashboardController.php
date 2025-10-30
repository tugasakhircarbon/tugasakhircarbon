<?php

namespace App\Http\Controllers;

use App\Models\CarbonCredit;
use App\Models\Transaction;
use App\Models\Payout;
use App\Models\SensorData;
use App\Models\Co2eData;
use App\Models\GpsData;
use App\Models\StatusLog;
use App\Services\EmissionTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $emissionTrackingService;

    public function __construct(EmissionTrackingService $emissionTrackingService)
    {
        $this->emissionTrackingService = $emissionTrackingService;
    }

    public function index()
    {
        if(Auth::id())
        {
            $role=Auth::user()->role;
            $user = Auth::user();
            
            $stats = [
                'pending_payouts' => Payout::where('user_id', $user->id)->where('status', 'pending')->count(),
            ];

            // Prepare vehicle carbon credits grouped by nrkb with effective quota (amount - daily_emissions_kg)
            $userCarbonCredits = CarbonCredit::where('owner_id', $user->id)
                                           ->withDevice()
                                           ->get();
            
            $effectiveVehicleQuotas = [];
            foreach ($userCarbonCredits as $credit) {
                if (!isset($effectiveVehicleQuotas[$credit->nrkb])) {
                    $effectiveVehicleQuotas[$credit->nrkb] = [
                        'nrkb' => $credit->nrkb,
                        'vehicle_type' => $credit->vehicle_type,
                        'total_amount' => 0,
                        'effective_quota' => 0,
                    ];
                }
                $dailyEmissions = $credit->daily_emissions_kg ?? 0;
                $effectiveVehicleQuotas[$credit->nrkb]['total_amount'] += $credit->amount ?? 0;
                $effectiveVehicleQuotas[$credit->nrkb]['effective_quota'] += max(0, ($credit->amount ?? 0) - $dailyEmissions);
            }
            
            $vehicleCarbonCredits = collect($effectiveVehicleQuotas);

            // Add transactions for dashboard
            if ($role == 'user') {
                $transactions = Transaction::with(['buyer'])
                    ->where(function($q) use ($user) {
                        $q->where('seller_id', $user->id)
                          ->orWhere('buyer_id', $user->id);
                    })
                    ->latest()
                    ->paginate(10);

                // Add emission monitoring data for user's vehicles
                $userCarbonCredits = CarbonCredit::where('owner_id', $user->id)
                                                ->withDevice()
                                                ->get();
                
                $emissionStats = $this->getUserEmissionStats($userCarbonCredits);
                
                return view('dashboard', compact('stats', 'transactions', 'vehicleCarbonCredits', 'emissionStats'));
            } else {
                $transactions = Transaction::with(['buyer'])->latest()->paginate(10);
            }
            
            if($role == 'admin')
            {
                $carbonCredits = CarbonCredit::where('owner_id', $user->id)->get();
                $availableAmountSum = 0;
                foreach ($carbonCredits as $credit) {
                    // Use quantity_to_sell directly as available amount
                    $available = $credit->quantity_to_sell;
                    Log::info("CarbonCredit ID: {$credit->id}, Quantity to Sell: {$credit->quantity_to_sell}");
                    $availableAmountSum += $available;
                }
                $stats['my_carbon_credits'] = $availableAmountSum;
                $stats['pending_approvals'] = CarbonCredit::where('status', 'pending')->count();
                $stats['total_transactions'] = Transaction::count();
                $stats['pending_payouts_admin'] = Payout::where('status', 'pending')->count();

                // Add comprehensive emission monitoring for admin
                $emissionDashboard = $this->getAdminEmissionDashboard();
                
                return view('admin.dashboard', compact('stats', 'emissionDashboard'));
            }
            else {
                return redirect()->back();
            }
        }
    }

    /**
     * Get emission statistics for user's vehicles dengan akumulasi yang akurat
     */
    private function getUserEmissionStats($carbonCredits)
    {
        $stats = [
            'total_devices' => $carbonCredits->count(),
            'active_devices' => 0,
            'today_emissions' => 0,
            'alerts_count' => 0,
            'vehicles' => []
        ];

        foreach ($carbonCredits as $credit) {
            if (!$credit->device_id) continue;

            // 🔥 GUNAKAN METHOD HELPER UNTUK AKUMULASI HARIAN
            $dailyAccumulation = Co2eData::getDailyAccumulation($credit->device_id);

            $vehicleStats = [
                'nrkb' => $credit->nrkb,
                'vehicle_type' => $credit->vehicle_type,
                'device_id' => $credit->device_id,
                'sensor_status' => $credit->sensor_status ?? 'inactive',
                // 'current_co2e_ppm' => $credit->current_co2e_ppm ?? 0, // Tidak diperlukan lagi
                'daily_co2e_sum' => $dailyAccumulation['total_co2e_mg_m3'], // Total CO2e mg/m³ hari ini (AKUMULASI)
                'daily_co2e_avg' => $dailyAccumulation['avg_co2e_mg_m3'], // Rata-rata CO2e mg/m³ hari ini
                'daily_co2e_max' => $dailyAccumulation['max_co2e_mg_m3'], // Maksimum CO2e mg/m³ hari ini
                'daily_record_count' => $dailyAccumulation['record_count'], // Jumlah record hari ini
                'daily_emissions_kg' => $dailyAccumulation['total_emissions_kg'], // Total emisi dalam kg
                'last_location' => null,
                'last_update' => $credit->last_sensor_update,
                'has_alert' => false
            ];

            // Check if device is active
            if ($credit->sensor_status === 'active') {
                $stats['active_devices']++;
            }

            // Add daily emissions (gunakan data dari akumulasi)
            $stats['today_emissions'] += $vehicleStats['daily_emissions_kg'];

            // Get latest GPS data
            $latestGps = GpsData::where('device_id', $credit->device_id)
                               ->latest('timestamp')
                               ->first();
            
            if ($latestGps) {
                $vehicleStats['last_location'] = [
                    'latitude' => $latestGps->latitude,
                    'longitude' => $latestGps->longitude,
                    'speed_kmph' => $latestGps->speed_kmph
                ];
            }

                // Check for alerts (gunakan data akumulasi untuk threshold)
            if ($vehicleStats['daily_emissions_kg'] > ($credit->emission_threshold_kg ?? 25) || 
                $vehicleStats['daily_co2e_sum'] > 100 || // Gunakan mg/m³ threshold
                $credit->sensor_status === 'error') {
                $vehicleStats['has_alert'] = true;
                $stats['alerts_count']++;
            }

            $stats['vehicles'][] = $vehicleStats;
        }

        return $stats;
    }

    /**
     * Get comprehensive emission dashboard for admin
     */
    private function getAdminEmissionDashboard()
    {
        // Get dashboard stats from service
        $dashboardStats = $this->emissionTrackingService->getDashboardStats();

        // Get recent alerts
        $recentAlerts = $this->emissionTrackingService->monitorEmissions();

        // Flatten alerts for the view
        $flattenedAlerts = [];
        foreach ($recentAlerts as $alertGroup) {
            foreach ($alertGroup['alerts'] as $alert) {
                $flattenedAlerts[] = [
                    'message' => $alert['message'],
                    'device_id' => $alert['device_id'],
                    'created_at' => now()->toDateTimeString(),
                    'severity' => $alert['severity'] ?? 'warning'
                ];
            }
        }

        // Get today's top emitters
        $todayReport = $this->emissionTrackingService->generateDailyEmissionReport();

        // Get device status overview
        $deviceOverview = CarbonCredit::withDevice()
                                    ->selectRaw('sensor_status, COUNT(*) as count')
                                    ->groupBy('sensor_status')
                                    ->get()
                                    ->pluck('count', 'sensor_status')
                                    ->toArray();

        // Get recent sensor data activity
        $recentActivity = SensorData::with('carbonCredit')
                                   ->latest('timestamp')
                                   ->limit(10)
                                   ->get();

        return [
            'stats' => $dashboardStats,
            'alerts' => [], // Removed alerts from admin dashboard
            'today_report' => $todayReport,
            'device_overview' => $deviceOverview,
            'recent_activity' => $recentActivity,
            'emission_chart_data' => $this->getEmissionChartData()
        ];
    }

    /**
     * Get data for emission charts
     */
    private function getEmissionChartData()
    {
        // Get last 7 days emission data
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayEmissions = Co2eData::whereDate('timestamp', $date)
                       ->get()
                       ->sum(function ($data) {
                           return Co2eData::convertMgM3ToKg($data->co2e_mg_m3);
                       });
            $chartData[] = [
                'date' => $date->format('M d'),
                'emissions' => round($dayEmissions, 2)
            ];
        }

        return $chartData;
    }

    /**
     * API endpoint for real-time emission data dengan akumulasi yang akurat
     */
    public function getEmissionData(Request $request)
    {
        $deviceId = $request->get('device_id');
        
        if ($deviceId) {
            // Get specific device data
            $carbonCredit = CarbonCredit::where('device_id', $deviceId)->first();
            if (!$carbonCredit) {
                return response()->json(['error' => 'Device not found'], 404);
            }

            // 🔥 GUNAKAN METHOD HELPER UNTUK AKUMULASI REAL-TIME
            $dailyAccumulation = Co2eData::getDailyAccumulation($deviceId);
            $monthlyAccumulation = Co2eData::getMonthlyAccumulation($deviceId);
            $totalAccumulation = Co2eData::getTotalAccumulation($deviceId);

            $data = [
                'device_id' => $deviceId,
                'nrkb' => $carbonCredit->nrkb,
                'vehicle_type' => $carbonCredit->vehicle_type,
                // 'current_co2e_ppm' => $carbonCredit->current_co2e_ppm ?? 0, // Tidak diperlukan lagi
                'sensor_status' => $carbonCredit->sensor_status ?? 'inactive',
                'last_update' => $carbonCredit->last_sensor_update,
                
                // 🔥 DATA AKUMULASI HARIAN
                'daily' => [
                    'total_co2e_mg_m3' => $dailyAccumulation['total_co2e_mg_m3'],
                    'avg_co2e_mg_m3' => $dailyAccumulation['avg_co2e_mg_m3'],
                    'max_co2e_mg_m3' => $dailyAccumulation['max_co2e_mg_m3'],
                    'min_co2e_mg_m3' => $dailyAccumulation['min_co2e_mg_m3'],
                    'record_count' => $dailyAccumulation['record_count'],
                    'emissions_kg' => $dailyAccumulation['total_emissions_kg'],
                    'date' => $dailyAccumulation['date']
                ],
                
                // 🔥 DATA AKUMULASI BULANAN
                'monthly' => [
                    'total_co2e_mg_m3' => $monthlyAccumulation['total_co2e_mg_m3'],
                    'avg_co2e_mg_m3' => $monthlyAccumulation['avg_co2e_mg_m3'],
                    'record_count' => $monthlyAccumulation['record_count'],
                    'emissions_kg' => $monthlyAccumulation['total_emissions_kg'],
                    'month' => $monthlyAccumulation['month'],
                    'year' => $monthlyAccumulation['year']
                ],
                
                // 🔥 DATA AKUMULASI TOTAL
                'total' => [
                    'total_co2e_mg_m3' => $totalAccumulation['total_co2e_mg_m3'],
                    'avg_co2e_mg_m3' => $totalAccumulation['avg_co2e_mg_m3'],
                    'record_count' => $totalAccumulation['record_count'],
                    'emissions_kg' => $totalAccumulation['total_emissions_kg']
                ],
                
                // LOKASI GPS
                'location' => [
                    'latitude' => $carbonCredit->last_latitude,
                    'longitude' => $carbonCredit->last_longitude,
                    'speed_kmph' => $carbonCredit->last_speed_kmph
                ],
                
                // THRESHOLD DAN ALERT
                'threshold' => [
                    'emission_threshold_kg' => $carbonCredit->emission_threshold_kg ?? 25,
                    'is_threshold_exceeded' => $dailyAccumulation['total_emissions_kg'] > ($carbonCredit->emission_threshold_kg ?? 25),
                    'is_high_co2e' => $dailyAccumulation['total_co2e_mg_m3'] > 100 // Gunakan mg/m³ threshold
                ]
            ];
        } else {
            // Get overall stats
            $data = $this->emissionTrackingService->getDashboardStats();
        }

        return response()->json($data);
    }

    /**
     * Show emission monitoring page
     */
    public function emissionMonitoring()
    {
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            // Admin sees all devices
            $carbonCredits = CarbonCredit::withDevice()->with(['sensorData', 'co2eData', 'gpsData'])->get();
            $emissionDashboard = $this->getAdminEmissionDashboard();
        } else {
            // User sees only their devices
            $carbonCredits = CarbonCredit::where('owner_id', $user->id)
                                       ->withDevice()
                                       ->with(['sensorData', 'co2eData', 'gpsData'])
                                       ->get();
            $emissionDashboard = $this->getUserEmissionStats($carbonCredits);
        }

        return view('emission-monitoring', compact('carbonCredits', 'emissionDashboard'));
    }
}
