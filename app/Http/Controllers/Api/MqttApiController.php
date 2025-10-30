<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MqttDataService;
use App\Services\CarbonCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MqttApiController extends Controller
{
    protected $mqttDataService;
    protected $carbonCalculationService;

    public function __construct(
        MqttDataService $mqttDataService,
        CarbonCalculationService $carbonCalculationService
    ) {
        $this->mqttDataService = $mqttDataService;
        $this->carbonCalculationService = $carbonCalculationService;
    }

    /**
     * Endpoint untuk menerima data sensor dari Python MQTT script
     */
    public function receiveSensorData(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'timestamp' => 'required|numeric',
                'environmental' => 'sometimes|array',
                'gases' => 'sometimes|array',
                'particulates' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 400);
            }

            $sensorData = $this->mqttDataService->processSensorData($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Data sensor berhasil diproses',
                'data' => [
                    'id' => $sensorData->id,
                    'device_id' => $sensorData->device_id,
                    'timestamp' => $sensorData->timestamp->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error processing sensor data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses data sensor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk menerima data CO2e dari Python MQTT script
     */
    public function receiveCo2eData(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'timestamp' => 'required|numeric',
                'co2e_mg_m3' => 'required|numeric|min:0',
                'contributors' => 'sometimes|array',
                'gwp_values' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data CO2e tidak valid',
                    'errors' => $validator->errors()
                ], 400);
            }

            $co2eData = $this->mqttDataService->processCo2eData($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Data CO2e berhasil diproses',
                'data' => [
                    'id' => $co2eData->id,
                    'device_id' => $co2eData->device_id,
                    'co2e_mg_m3' => $co2eData->co2e_mg_m3,
                    'timestamp' => $co2eData->timestamp->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error processing CO2e data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses data CO2e',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk menerima data GPS dari Python MQTT script
     */
    public function receiveGpsData(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'timestamp' => 'required|numeric',
                'location' => 'required|array',
                'location.latitude' => 'required|numeric|between:-90,90',
                'location.longitude' => 'required|numeric|between:-180,180',
                'location.speed_kmph' => 'sometimes|numeric|min:0',
                'datetime' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data GPS tidak valid',
                    'errors' => $validator->errors()
                ], 400);
            }

            $gpsData = $this->mqttDataService->processGpsData($request->all());

            if (!$gpsData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data GPS tidak valid atau koordinat kosong'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data GPS berhasil diproses',
                'data' => [
                    'id' => $gpsData->id,
                    'device_id' => $gpsData->device_id,
                    'latitude' => $gpsData->latitude,
                    'longitude' => $gpsData->longitude,
                    'speed_kmph' => $gpsData->speed_kmph,
                    'timestamp' => $gpsData->timestamp->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error processing GPS data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses data GPS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk menerima status log dari Python MQTT script
     */
    public function receiveStatusLog(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'timestamp' => 'required|numeric',
                'status' => 'required|string',
                'ip_address' => 'sometimes|ip',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data status tidak valid',
                    'errors' => $validator->errors()
                ], 400);
            }

            $statusLog = $this->mqttDataService->processStatusLog($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Status log berhasil diproses',
                'data' => [
                    'id' => $statusLog->id,
                    'device_id' => $statusLog->device_id,
                    'status' => $statusLog->status,
                    'timestamp' => $statusLog->timestamp->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error processing status log: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses status log',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk mendapatkan data terbaru dari device
     */
    public function getLatestData(Request $request, $deviceId): JsonResponse
    {
        try {
            $data = $this->mqttDataService->getLatestData($deviceId);

            return response()->json([
                'success' => true,
                'device_id' => $deviceId,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting latest data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data terbaru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk mendapatkan statistik emisi
     */
    public function getEmissionStats(Request $request, $deviceId): JsonResponse
    {
        try {
            $period = $request->get('period', 'daily'); // daily, weekly, monthly
            
            $stats = $this->mqttDataService->getEmissionStats($deviceId, $period);

            return response()->json([
                'success' => true,
                'device_id' => $deviceId,
                'period' => $period,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting emission stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik emisi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk batch processing multiple data types
     */
    public function receiveBatchData(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'batch_data' => 'required|array',
                'batch_data.*.type' => 'required|in:sensor,co2e,gps,status',
                'batch_data.*.data' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data batch tidak valid',
                    'errors' => $validator->errors()
                ], 400);
            }

            $results = [];
            $deviceId = $request->get('device_id');

            foreach ($request->get('batch_data') as $item) {
                $data = array_merge($item['data'], ['device_id' => $deviceId]);
                
                try {
                    switch ($item['type']) {
                        case 'sensor':
                            $result = $this->mqttDataService->processSensorData($data);
                            break;
                        case 'co2e':
                            $result = $this->mqttDataService->processCo2eData($data);
                            break;
                        case 'gps':
                            $result = $this->mqttDataService->processGpsData($data);
                            break;
                        case 'status':
                            $result = $this->mqttDataService->processStatusLog($data);
                            break;
                        default:
                            throw new \Exception("Unknown data type: {$item['type']}");
                    }

                    $results[] = [
                        'type' => $item['type'],
                        'success' => true,
                        'id' => $result ? $result->id : null
                    ];

                } catch (\Exception $e) {
                    $results[] = [
                        'type' => $item['type'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $totalCount = count($results);

            return response()->json([
                'success' => $successCount > 0,
                'message' => "Berhasil memproses {$successCount} dari {$totalCount} data",
                'device_id' => $deviceId,
                'results' => $results
            ], $successCount > 0 ? 201 : 400);

        } catch (\Exception $e) {
            Log::error('Error processing batch data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses data batch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check endpoint untuk Python script
     */
    public function healthCheck(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'MQTT API is running',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }

    /**
     * Endpoint untuk mendapatkan konfigurasi device
     */
    public function getDeviceConfig($deviceId): JsonResponse
    {
        try {
            $carbonCredit = \App\Models\CarbonCredit::where('device_id', $deviceId)->first();

            if (!$carbonCredit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'device_id' => $deviceId,
                'config' => [
                    'auto_adjustment_enabled' => $carbonCredit->auto_adjustment_enabled,
                    'emission_threshold_kg' => $carbonCredit->emission_threshold_kg,
                    'vehicle_type' => $carbonCredit->vehicle_type,
                    'nrkb' => $carbonCredit->nrkb,
                    'current_quota' => $carbonCredit->quantity_to_sell,
                    'sensor_status' => $carbonCredit->sensor_status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting device config: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil konfigurasi device',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
