<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\Api\MqttApiController;

// Existing routes
Route::post('/midtrans/notification', [TransactionController::class, 'handlePaymentNotification'])->name('midtrans.notification');
Route::post('/payout-notification', [PayoutController::class, 'handlePayoutNotification'])
    ->name('payout.notification');

// MQTT API Routes untuk integrasi dengan Python script
Route::prefix('mqtt')->name('mqtt.')->group(function () {
    // Health check
    Route::get('/health', [MqttApiController::class, 'healthCheck'])->name('health');
    
    // Data ingestion endpoints
    Route::post('/sensor-data', [MqttApiController::class, 'receiveSensorData'])->name('sensor.data');
    Route::post('/co2e-data', [MqttApiController::class, 'receiveCo2eData'])->name('co2e.data');
    Route::post('/gps-data', [MqttApiController::class, 'receiveGpsData'])->name('gps.data');
    Route::post('/status-log', [MqttApiController::class, 'receiveStatusLog'])->name('status.log');
    
    // Batch processing
    Route::post('/batch-data', [MqttApiController::class, 'receiveBatchData'])->name('batch.data');
    
    // Data retrieval endpoints
    Route::get('/device/{deviceId}/latest', [MqttApiController::class, 'getLatestData'])->name('device.latest');
    Route::get('/device/{deviceId}/stats', [MqttApiController::class, 'getEmissionStats'])->name('device.stats');
    Route::get('/device/{deviceId}/config', [MqttApiController::class, 'getDeviceConfig'])->name('device.config');
});
