<?php

namespace App\Jobs;

use App\Services\MqttDataService;
use App\Services\EmissionTrackingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMqttData implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 menit timeout
    public $tries = 3; // Maksimal 3 kali retry

    protected $dataType;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(string $dataType, array $data)
    {
        $this->dataType = $dataType;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(MqttDataService $mqttDataService, EmissionTrackingService $emissionTrackingService): void
    {
        try {
            Log::info("Processing MQTT data job", [
                'type' => $this->dataType,
                'device_id' => $this->data['device_id'] ?? 'unknown'
            ]);

            // Process data berdasarkan tipe
            $result = null;
            switch ($this->dataType) {
                case 'sensor':
                    $result = $mqttDataService->processSensorData($this->data);
                    break;
                
                case 'co2e':
                    $result = $mqttDataService->processCo2eData($this->data);
                    break;
                
                case 'gps':
                    $result = $mqttDataService->processGpsData($this->data);
                    break;
                
                case 'status':
                    $result = $mqttDataService->processStatusLog($this->data);
                    break;
                
                default:
                    throw new \InvalidArgumentException("Unknown data type: {$this->dataType}");
            }

            // Update emission tracking jika ada device_id
            if (isset($this->data['device_id']) && $result) {
                $carbonCredit = \App\Models\CarbonCredit::where('device_id', $this->data['device_id'])->first();
                if ($carbonCredit) {
                    $emissionTrackingService->updateCarbonCreditEmissions($carbonCredit);
                }
            }

            Log::info("MQTT data processed successfully", [
                'type' => $this->dataType,
                'device_id' => $this->data['device_id'] ?? 'unknown',
                'result_id' => $result ? $result->id : null
            ]);

        } catch (\Exception $e) {
            Log::error("Error processing MQTT data job", [
                'type' => $this->dataType,
                'device_id' => $this->data['device_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Re-throw exception untuk retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("MQTT data job failed permanently", [
            'type' => $this->dataType,
            'device_id' => $this->data['device_id'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // TODO: Bisa ditambahkan notifikasi ke admin atau sistem monitoring
    }
}
