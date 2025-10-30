@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto p-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('devices.index') }}" class="text-gray-600 hover:text-gray-800 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="text-3xl font-bold flex items-center space-x-3 text-primary">
            <i class="fas fa-plus-circle"></i>
            <span>Daftarkan Device Sensor</span>
        </h1>
    </div>

    <!-- Vehicle Info -->
    <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg mb-6">
        <div class="flex items-center space-x-3">
            <i class="fas {{ $carbonCredit->vehicle_type === 'motorcycle' ? 'fa-motorcycle' : 'fa-car' }} text-blue-600 text-xl"></i>
            <div>
                <h3 class="font-semibold text-blue-800">{{ $carbonCredit->nrkb }}</h3>
                <p class="text-sm text-blue-600">{{ ucfirst($carbonCredit->vehicle_type) }} - {{ $carbonCredit->owner->name }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
        <form action="{{ route('devices.store', $carbonCredit) }}" method="POST">
            @csrf
            
            <div class="space-y-6">
                <!-- Device ID -->
                <div>
                    <label for="device_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Device ID <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="device_id" 
                           name="device_id" 
                           value="{{ old('device_id') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('device_id') border-red-500 @enderror"
                           placeholder="Contoh: SENSOR_001, DEVICE_ABC123"
                           required>
                    @error('device_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i>
                        ID unik untuk device sensor. Pastikan sesuai dengan label pada device fisik.
                    </p>
                </div>

                <!-- Emission Threshold -->
                <div>
                    <label for="emission_threshold_kg" class="block text-sm font-medium text-gray-700 mb-2">
                        Batas Emisi Harian (kg) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" 
                           id="emission_threshold_kg" 
                           name="emission_threshold_kg" 
                           value="{{ old('emission_threshold_kg', $carbonCredit->vehicle_type === 'motorcycle' ? '15' : '25') }}"
                           step="0.1"
                           min="0"
                           max="1000"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('emission_threshold_kg') border-red-500 @enderror"
                           required>
                    @error('emission_threshold_kg')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">
                        <i class="fas fa-info-circle"></i>
                        Jika emisi harian melebihi batas ini, sistem akan mengirim alert dan mengurangi kuota karbon.
                    </p>
                </div>

                <!-- Recommended Thresholds -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-800 mb-2">
                        <i class="fas fa-lightbulb text-yellow-500"></i>
                        Rekomendasi Batas Emisi
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-motorcycle text-blue-500"></i>
                            <span><strong>Motor:</strong> 10-20 kg/hari</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-car text-blue-500"></i>
                            <span><strong>Mobil:</strong> 20-35 kg/hari</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-truck text-blue-500"></i>
                            <span><strong>Truk:</strong> 50-100 kg/hari</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-bus text-blue-500"></i>
                            <span><strong>Bus:</strong> 80-150 kg/hari</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Catatan (Opsional)
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror"
                              placeholder="Catatan tambahan tentang device atau instalasi...">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Installation Instructions -->
                <div class="bg-green-50 border border-green-200 p-4 rounded-lg">
                    <h4 class="font-medium text-green-800 mb-3">
                        <i class="fas fa-tools text-green-600"></i>
                        Langkah Instalasi Device
                    </h4>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-green-700">
                        <li>Pastikan device sensor sudah dikalibrasi dan berfungsi normal</li>
                        <li>Pasang device pada lokasi yang tepat di kendaraan (dekat exhaust pipe)</li>
                        <li>Hubungkan device ke power supply kendaraan (12V DC)</li>
                        <li>Pastikan device terhubung ke internet (WiFi/4G)</li>
                        <li>Setelah registrasi, scan QR code untuk konfigurasi otomatis</li>
                        <li>Test device dengan menjalankan kendaraan selama 5-10 menit</li>
                        <li>Verifikasi data masuk ke sistem monitoring</li>
                    </ol>
                </div>

                <!-- Technical Specifications -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-medium text-gray-800 mb-3">
                        <i class="fas fa-cog text-gray-600"></i>
                        Spesifikasi Teknis
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                        <div>
                            <strong>MQTT Broker:</strong> test.mosquitto.org:1883
                        </div>
                        <div>
                            <strong>API Endpoint:</strong> {{ url('/api/mqtt') }}
                        </div>
                        <div>
                            <strong>Data Interval:</strong> 30 detik
                        </div>
                        <div>
                            <strong>Power:</strong> 12V DC (dari kendaraan)
                        </div>
                        <div>
                            <strong>Sensor Types:</strong> CO2, CH4, N2O, CO, PM
                        </div>
                        <div>
                            <strong>GPS:</strong> Built-in GPS module
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                    <a href="{{ route('devices.index') }}" 
                       class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Kembali
                    </a>
                    
                    <div class="flex items-center space-x-3">
                        <button type="button" 
                                onclick="resetForm()"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium">
                            <i class="fas fa-undo mr-1"></i>
                            Reset
                        </button>
                        
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                            <i class="fas fa-save mr-1"></i>
                            Daftarkan Device
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
function resetForm() {
    if (confirm('Apakah Anda yakin ingin mereset form?')) {
        document.querySelector('form').reset();
        
        // Reset to default threshold based on vehicle type
        const vehicleType = '{{ $carbonCredit->vehicle_type }}';
        const thresholdInput = document.getElementById('emission_threshold_kg');
        
        if (vehicleType === 'motorcycle') {
            thresholdInput.value = '15';
        } else {
            thresholdInput.value = '25';
        }
    }
}

// Auto-generate device ID suggestion
document.addEventListener('DOMContentLoaded', function() {
    const deviceIdInput = document.getElementById('device_id');
    const vehicleNrkb = '{{ $carbonCredit->nrkb }}';
    
    if (!deviceIdInput.value) {
        // Generate suggestion based on NRKB
        const suggestion = 'SENSOR_' + vehicleNrkb.replace(/\s+/g, '').toUpperCase();
        deviceIdInput.placeholder = `Saran: ${suggestion}`;
    }
});

// Validate device ID format
document.getElementById('device_id').addEventListener('input', function(e) {
    const value = e.target.value;
    const isValid = /^[A-Z0-9_-]+$/i.test(value);
    
    if (value && !isValid) {
        e.target.setCustomValidity('Device ID hanya boleh mengandung huruf, angka, underscore (_), dan dash (-)');
    } else {
        e.target.setCustomValidity('');
    }
});
</script>
@endsection
