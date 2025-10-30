# 🔥 Auto CO2e Calculation System

## 📋 Overview

Sistem ini telah diupgrade untuk **otomatis menghitung CO2 equivalent (CO2e)** dari data sensor yang masuk melalui MQTT. Sebelumnya, CO2e harus dikirim secara terpisah, sekarang sistem akan menghitung CO2e secara otomatis berdasarkan nilai-nilai gas dari sensor.

## 🚀 Fitur Baru

### ✅ **Automatic CO2e Calculation**
- Sistem otomatis menghitung CO2e dari data sensor (CO2, CH4, N2O, CO)
- Menggunakan Global Warming Potential (GWP) values yang akurat
- Menyimpan hasil perhitungan ke database secara otomatis
- Logging lengkap untuk tracking perhitungan

### ✅ **Enhanced MqttDataService**
- Integrasi dengan `CarbonCalculationService`
- Method `autoCalculateAndStoreCo2e()` untuk perhitungan otomatis
- Validasi data gas sebelum perhitungan
- Error handling yang robust

### ✅ **Comprehensive Logging**
- Log setiap perhitungan CO2e dengan detail
- Tracking kontribusi masing-masing gas
- Method perhitungan (auto vs manual)
- Error logging untuk debugging

## 🧮 Formula Perhitungan CO2e

### **Global Warming Potential (GWP) Values:**
```
CO2  = 1    (baseline)
CH4  = 25   (25x lebih berbahaya dari CO2)
N2O  = 298  (298x lebih berbahaya dari CO2)
CO   = 3    (3x lebih berbahaya dari CO2)
```

### **Formula:**
```
CO2e (PPM) = (CO2_ppm × 1) + (CH4_ppm × 25) + (N2O_ppm × 298) + (CO_ppm × 3)
```

### **Contoh Perhitungan:**
```
Input Sensor:
- CO2: 400 PPM
- CH4: 2.0 PPM  
- N2O: 0.3 PPM
- CO: 20 PPM

Perhitungan:
- CO2 Contribution: 400 × 1 = 400 PPM
- CH4 Contribution: 2.0 × 25 = 50 PPM
- N2O Contribution: 0.3 × 298 = 89.4 PPM
- CO Contribution: 20 × 3 = 60 PPM

Total CO2e = 400 + 50 + 89.4 + 60 = 599.4 PPM
```

## 🔄 Alur Kerja Sistem

### **1. Data Sensor Masuk**
```
MQTT Topic: sensors/emission/data
Data: {
  "device_id": "123456",
  "timestamp": 1640995200000,
  "gases": {
    "co2_ppm": 400,
    "ch4_ppm": 2.0,
    "n2o_ppm": 0.3,
    "co_ppm": 20
  }
}
```

### **2. Automatic Processing**
```php
// MqttDataService.php
public function processSensorData(array $data) {
    // 1. Simpan sensor data
    $sensorData = SensorData::create([...]);
    
    // 2. 🔥 AUTO CALCULATE CO2e
    $this->autoCalculateAndStoreCo2e($sensorData);
    
    // 3. Update carbon credit
    $this->updateCarbonCreditFromSensor($deviceId, $sensorData);
}
```

### **3. CO2e Calculation**
```php
// autoCalculateAndStoreCo2e()
private function autoCalculateAndStoreCo2e(SensorData $sensorData) {
    // 1. Validasi data gas
    if (!$this->hasValidGasData($sensorData)) return null;
    
    // 2. Hitung CO2e menggunakan CarbonCalculationService
    $co2eCalculation = $this->carbonCalculationService->calculateCo2Equivalent($sensorData);
    
    // 3. Simpan hasil ke database
    $co2eData = Co2eData::create([...]);
    
    // 4. Update carbon credit
    $this->updateCarbonCreditFromCo2e($deviceId, $co2eData);
}
```

### **4. Database Storage**
```sql
-- Tabel co2e_data akan otomatis terisi:
INSERT INTO co2e_data (
    device_id, timestamp, co2e_ppm,
    co2_contribution, ch4_contribution, 
    n2o_contribution, co_contribution,
    gwp_co2, gwp_ch4, gwp_n2o
) VALUES (
    '123456', '2024-01-01 12:00:00', 599.4,
    400, 50, 89.4, 60,
    1, 25, 298
);
```

## 🧪 Testing

### **1. Manual Test dengan Script**
```bash
# Test auto calculation
python test_auto_co2e_calculation.py
```

### **2. MQTT Test**
```bash
# Jalankan MQTT integration
python mqtt_laravel_integration.py

# Kirim data sensor (di terminal lain)
python test_device_123456.py
```

### **3. API Test**
```bash
# Test langsung ke API
curl -X POST http://localhost:8000/api/mqtt/sensor-data \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "123456",
    "timestamp": 1640995200000,
    "gases": {
      "co2_ppm": 400,
      "ch4_ppm": 2.0,
      "n2o_ppm": 0.3,
      "co_ppm": 20
    }
  }'
```

## 📊 Monitoring & Verification

### **1. Cek Log Laravel**
```bash
tail -f storage/logs/laravel.log | grep "CO2e"
```

### **2. Cek Database**
```sql
-- Cek data sensor terbaru
SELECT * FROM sensor_data WHERE device_id = '123456' ORDER BY timestamp DESC LIMIT 5;

-- Cek CO2e yang dihitung otomatis
SELECT * FROM co2e_data WHERE device_id = '123456' ORDER BY timestamp DESC LIMIT 5;

-- Cek relasi sensor -> co2e
SELECT 
    s.timestamp as sensor_time,
    s.co2_ppm, s.ch4_ppm, s.n2o_ppm, s.co_ppm,
    c.co2e_ppm,
    c.co2_contribution, c.ch4_contribution, c.n2o_contribution, c.co_contribution
FROM sensor_data s
LEFT JOIN co2e_data c ON s.device_id = c.device_id AND s.timestamp = c.timestamp
WHERE s.device_id = '123456'
ORDER BY s.timestamp DESC
LIMIT 10;
```

### **3. Dashboard Monitoring**
- Buka: `http://localhost:8000/emission-monitoring`
- Lihat data real-time CO2e untuk device 123456
- Verifikasi bahwa CO2e muncul otomatis setelah sensor data masuk

## 🔧 Configuration

### **1. GWP Values (dapat disesuaikan)**
```php
// app/Services/CarbonCalculationService.php
const GWP_VALUES = [
    'co2' => 1,      // CO2 sebagai baseline
    'ch4' => 25,     // Metana (dapat diubah sesuai standar)
    'n2o' => 298,    // Nitrous Oxide
    'co' => 3,       // Carbon Monoxide
];
```

### **2. Validation Rules**
```php
// app/Services/MqttDataService.php
private function hasValidGasData(SensorData $sensorData) {
    // Minimal harus ada salah satu gas utama > 0
    return ($sensorData->co2_ppm !== null && $sensorData->co2_ppm > 0) ||
           ($sensorData->ch4_ppm !== null && $sensorData->ch4_ppm > 0) ||
           ($sensorData->n2o_ppm !== null && $sensorData->n2o_ppm > 0) ||
           ($sensorData->co_ppm !== null && $sensorData->co_ppm > 0);
}
```

## 🚨 Troubleshooting

### **Problem: CO2e tidak dihitung otomatis**
**Solution:**
```bash
# 1. Pastikan queue worker berjalan
php artisan queue:work

# 2. Cek log error
tail -f storage/logs/laravel.log

# 3. Test manual calculation
php artisan tinker
>>> $sensor = App\Models\SensorData::latest()->first();
>>> $service = new App\Services\CarbonCalculationService();
>>> $result = $service->calculateCo2Equivalent($sensor);
>>> dd($result);
```

### **Problem: Data sensor tidak valid**
**Solution:**
```bash
# Cek data sensor yang masuk
SELECT device_id, co2_ppm, ch4_ppm, n2o_ppm, co_ppm 
FROM sensor_data 
WHERE device_id = '123456' 
ORDER BY timestamp DESC LIMIT 5;

# Pastikan minimal ada satu gas > 0
```

### **Problem: Dependency injection error**
**Solution:**
```bash
# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart queue worker
php artisan queue:restart
```

## 📈 Benefits

### **1. Akurasi Tinggi**
- Perhitungan berdasarkan standar GWP internasional
- Konsistensi formula di seluruh sistem
- Validasi data sebelum perhitungan

### **2. Efisiensi**
- Otomatis, tidak perlu manual calculation
- Real-time processing
- Mengurangi beban sensor device

### **3. Transparency**
- Log lengkap setiap perhitungan
- Breakdown kontribusi masing-masing gas
- Audit trail untuk compliance

### **4. Scalability**
- Dapat handle multiple devices
- Background processing dengan queue
- Extensible untuk gas lainnya

## 🎯 Next Steps

### **1. Enhanced Features**
- [ ] Temperature correction factor
- [ ] Altitude adjustment
- [ ] Vehicle-specific emission factors
- [ ] Real-time alerts untuk CO2e tinggi

### **2. Optimization**
- [ ] Batch processing untuk multiple sensors
- [ ] Caching untuk frequent calculations
- [ ] Database indexing optimization
- [ ] API rate limiting

### **3. Integration**
- [ ] Export data ke format standar (CSV, JSON)
- [ ] Integration dengan carbon offset marketplace
- [ ] Real-time dashboard dengan WebSocket
- [ ] Mobile app notifications

## 📞 Support

Jika ada pertanyaan atau masalah:
1. Cek log Laravel: `storage/logs/laravel.log`
2. Cek log MQTT: `mqtt_laravel_integration.log`
3. Test dengan script: `python test_auto_co2e_calculation.py`
4. Verifikasi database: Query SQL di atas

**Sistem sekarang sudah otomatis menghitung CO2e dari nilai-nilai sensor! 🎉**
