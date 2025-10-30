<?php

namespace App\Http\Controllers;

use App\Models\CarbonCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    /**
     * Show device registration form
     */
    public function index()
    {
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            // Admin sees all vehicles
            $carbonCredits = CarbonCredit::with('owner')->get();
        } else {
            // User sees only their vehicles
            $carbonCredits = CarbonCredit::where('owner_id', $user->id)->get();
        }

        return view('devices.index', compact('carbonCredits'));
    }

    /**
     * Show form to register device for a vehicle
     */
    public function create(CarbonCredit $carbonCredit)
    {
        // Check authorization
        if (Auth::user()->role !== 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('devices.create', compact('carbonCredit'));
    }

    /**
     * Register device for a vehicle
     */
    public function store(Request $request, CarbonCredit $carbonCredit)
    {
        // Check authorization
        if (Auth::user()->role !== 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'device_id' => 'required|string|unique:carbon_credits,device_id|max:50',
            'emission_threshold_kg' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:500'
        ]);

        // Update carbon credit with device information
        $carbonCredit->update([
            'device_id' => $validated['device_id'],
            'emission_threshold_kg' => $validated['emission_threshold_kg'],
            'sensor_status' => 'inactive', // Will be updated when sensor starts sending data
            'auto_adjustment_enabled' => true,
            'device_registered_at' => now(),
            'device_notes' => $validated['notes'] ?? null
        ]);

        return redirect()->route('devices.index')
            ->with('success', 'Device berhasil didaftarkan untuk kendaraan ' . $carbonCredit->nrkb);
    }

    /**
     * Show device details
     */
    public function show(CarbonCredit $carbonCredit)
    {
        // Check authorization
        if (Auth::user()->role !== 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if (!$carbonCredit->device_id) {
            return redirect()->route('devices.index')
                ->with('error', 'Kendaraan ini belum memiliki device sensor.');
        }

        // Get recent sensor data
        $recentSensorData = $carbonCredit->sensorData()
                                       ->latest('timestamp')
                                       ->limit(10)
                                       ->get();

        $recentCo2eData = $carbonCredit->co2eData()
                                     ->latest('timestamp')
                                     ->limit(10)
                                     ->get();

        $recentGpsData = $carbonCredit->gpsData()
                                    ->latest('timestamp')
                                    ->limit(10)
                                    ->get();

        return view('devices.show', compact('carbonCredit', 'recentSensorData', 'recentCo2eData', 'recentGpsData'));
    }

    /**
     * Edit device settings
     */
    public function edit(CarbonCredit $carbonCredit)
    {
        // Check authorization
        if (Auth::user()->role !== 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if (!$carbonCredit->device_id) {
            return redirect()->route('devices.index')
                ->with('error', 'Kendaraan ini belum memiliki device sensor.');
        }

        return view('devices.edit', compact('carbonCredit'));
    }

    /**
     * Update device settings
     */
    public function update(Request $request, CarbonCredit $carbonCredit)
    {
        // Check authorization
        if (Auth::user()->role !== 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'emission_threshold_kg' => 'required|numeric|min:0|max:1000',
            'auto_adjustment_enabled' => 'boolean',
            'notes' => 'nullable|string|max:500'
        ]);

        $carbonCredit->update([
            'emission_threshold_kg' => $validated['emission_threshold_kg'],
            'auto_adjustment_enabled' => $validated['auto_adjustment_enabled'] ?? false,
            'device_notes' => $validated['notes'] ?? null
        ]);

        return redirect()->route('devices.show', $carbonCredit)
            ->with('success', 'Pengaturan device berhasil diupdate.');
    }

    /**
     * Unregister device from vehicle
     */
    public function destroy(CarbonCredit $carbonCredit)
    {
        // Check authorization
        if (Auth::user()->role !== 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $carbonCredit->update([
            'device_id' => null,
            'sensor_status' => null,
            'emission_threshold_kg' => null,
            'auto_adjustment_enabled' => false,
            'current_co2e_mg_m3' => null,
            'daily_emissions_kg' => null,
            'last_sensor_update' => null,
            'last_latitude' => null,
            'last_longitude' => null,
            'last_speed_kmph' => null,
            'device_registered_at' => null,
            'device_notes' => null
        ]);

        return redirect()->route('devices.index')
            ->with('success', 'Device berhasil dilepas dari kendaraan ' . $carbonCredit->nrkb);
    }

    /**
     * Generate QR code for device setup
     */
    public function generateQrCode(CarbonCredit $carbonCredit)
    {
        if (!$carbonCredit->device_id) {
            return response()->json(['error' => 'Device not registered'], 400);
        }

        $setupData = [
            'device_id' => $carbonCredit->device_id,
            'vehicle_nrkb' => $carbonCredit->nrkb,
            'vehicle_type' => $carbonCredit->vehicle_type,
            'api_endpoint' => url('/api/mqtt'),
            'mqtt_topics' => [
                'sensor_data' => 'sensors/emission/data',
                'co2e_data' => 'sensors/emission/co2e',
                'gps_data' => 'sensors/gps/location',
                'status' => 'sensors/emission/status'
            ]
        ];

        return response()->json([
            'qr_data' => base64_encode(json_encode($setupData)),
            'setup_url' => route('devices.setup', $carbonCredit->device_id)
        ]);
    }

    /**
     * Device setup page (for technicians)
     */
    public function setup($deviceId)
    {
        $carbonCredit = CarbonCredit::where('device_id', $deviceId)->first();
        
        if (!$carbonCredit) {
            abort(404, 'Device not found');
        }

        $setupInstructions = [
            'device_id' => $deviceId,
            'mqtt_broker' => 'test.mosquitto.org',
            'mqtt_port' => 1883,
            'api_endpoint' => url('/api/mqtt'),
            'topics' => [
                'sensors/emission/data',
                'sensors/emission/co2e', 
                'sensors/gps/location',
                'sensors/emission/status'
            ]
        ];

        return view('devices.setup', compact('carbonCredit', 'setupInstructions'));
    }
}
