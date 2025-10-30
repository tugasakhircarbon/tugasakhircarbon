# TODO: Cleanup PPM-based Code to mg/m³-based

## Progress Tracking

### 1. Models
- [ ] app/Models/Co2eData.php - Remove convertPpmToKg and convertPpmToMgPerM3 methods
- [ ] app/Models/CarbonCredit.php - Replace co2e_ppm references with co2e_mg_m3

### 2. Services  
- [ ] app/Services/CarbonCalculationService.php - Replace convertPpmToKg with convertMgM3ToKg
- [ ] app/Services/EmissionTrackingService.php - Update PPM references to mg/m³
- [ ] app/Services/MqttDataService.php - Update PPM references to mg/m³

### 3. Controllers
- [ ] app/Http/Controllers/DashboardController.php - Update PPM references
- [ ] app/Http/Controllers/DeviceController.php - Update PPM references  
- [ ] app/Http/Controllers/Api/MqttApiController.php - Update PPM references

### 4. Testing
- [ ] Test emission calculations
- [ ] Test dashboard displays
- [ ] Test API responses

## Changes Made:
(Will be updated as we progress)
