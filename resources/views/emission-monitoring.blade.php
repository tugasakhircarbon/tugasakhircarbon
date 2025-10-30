@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold flex items-center space-x-3 text-primary">
            <i class="fas fa-chart-line"></i>
            <span>Monitoring Emisi Real-time</span>
        </h1>
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm text-gray-600">Live Data</span>
            </div>
            <button onclick="refreshData()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Dashboard Stats -->
    @if(Auth::user()->role === 'admin')
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Devices</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $emissionDashboard['stats']['total_devices'] ?? 0 }}</p>
                </div>
                <i class="fas fa-microchip text-blue-500 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Active Devices</p>
                    <p class="text-2xl font-bold text-green-600">{{ $emissionDashboard['stats']['active_devices'] ?? 0 }}</p>
                </div>
                <i class="fas fa-wifi text-green-500 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Today's Emissions</p>
                    <p class="text-2xl font-bold text-orange-600">{{ number_format($emissionDashboard['stats']['total_emissions_today'] ?? 0, 2) }} kg</p>
                </div>
                <i class="fas fa-smog text-orange-500 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Active Alerts</p>
                    <p class="text-2xl font-bold {{ ($emissionDashboard['stats']['active_alerts'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ $emissionDashboard['stats']['active_alerts'] ?? 0 }}
                    </p>
                </div>
                <i class="fas fa-exclamation-triangle {{ ($emissionDashboard['stats']['active_alerts'] ?? 0) > 0 ? 'text-red-500' : 'text-green-500' }} text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Emission Chart -->
    <div class="bg-white p-6 rounded-xl shadow border border-gray-100 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Trend Emisi 7 Hari Terakhir</h3>
        <canvas id="emissionChart" width="400" height="100"></canvas>
    </div>

    <!-- Recent Alerts -->
    @if(isset($emissionDashboard['alerts']) && count($emissionDashboard['alerts']) > 0)
    <div class="bg-white p-6 rounded-xl shadow border border-gray-100 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
            <i class="fas fa-exclamation-triangle text-red-500"></i>
            <span>Alert Terbaru</span>
        </h3>
        <div class="space-y-3">
            @foreach($emissionDashboard['alerts'] as $alert)
            <div class="flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <div>
                        <p class="font-medium text-red-800">{{ $alert['message'] }}</p>
                        <p class="text-sm text-red-600">Device: {{ $alert['device_id'] }}</p>
                    </div>
                </div>
                <div class="text-sm text-red-600">
                    {{ $alert['created_at'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @endif

    <!-- Vehicle Monitoring -->
    <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Status Kendaraan</h3>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Aktif</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Idle</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Error</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
                    <span class="text-sm text-gray-600">Offline</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kendaraan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CO2e mg/m³ (Total Hari Ini)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emisi Harian</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Update Terakhir</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($carbonCredits as $credit)
                    @if($credit->device_id)
                    <tr class="hover:bg-gray-50" id="vehicle-{{ $credit->device_id }}">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <i class="fas {{ $credit->vehicle_type === 'motorcycle' ? 'fa-motorcycle' : 'fa-car' }} text-blue-500"></i>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $credit->nrkb }}</p>
                                    <p class="text-sm text-gray-600">{{ ucfirst($credit->vehicle_type) }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $credit->device_id }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 rounded-full {{ 
                                    $credit->sensor_status === 'active' ? 'bg-green-500' : 
                                    ($credit->sensor_status === 'error' ? 'bg-red-500' : 
                                    ($credit->sensor_status === 'idle' ? 'bg-yellow-500' : 'bg-gray-400')) 
                                }}"></div>
                                <span class="text-sm capitalize {{ 
                                    $credit->sensor_status === 'active' ? 'text-green-600' : 
                                    ($credit->sensor_status === 'error' ? 'text-red-600' : 
                                    ($credit->sensor_status === 'idle' ? 'text-yellow-600' : 'text-gray-600')) 
                                }}">
                                    {{ $credit->sensor_status ?? 'offline' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @php
                                // 🔥 GUNAKAN METHOD HELPER UNTUK AKUMULASI YANG AKURAT
                                $dailyAccumulation = \App\Models\Co2eData::getDailyAccumulation($credit->device_id);
                            @endphp
                            <div class="flex flex-col">
                                <span class="text-sm font-bold {{ ($dailyAccumulation['total_co2e_mg_m3'] ?? 0) > 100 ? 'text-red-600' : (($dailyAccumulation['total_co2e_mg_m3'] ?? 0) > 50 ? 'text-orange-600' : 'text-blue-600') }}">
                                    {{ number_format($dailyAccumulation['total_co2e_mg_m3'] ?? 0, 1) }} mg/m³
                                </span>
                                <div class="text-xs text-gray-500">
                                    ({{ $dailyAccumulation['record_count'] }} records)
                                </div>
                                @if($dailyAccumulation['record_count'] > 0)
                                <div class="text-xs text-gray-400">
                                    Avg: {{ number_format($dailyAccumulation['avg_co2e_mg_m3'] ?? 0, 1) }} | 
                                    Max: {{ number_format($dailyAccumulation['max_co2e_mg_m3'] ?? 0, 1) }}
                                </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm font-semibold text-orange-600">
                                {{ number_format($credit->daily_emissions_kg ?? 0, 2) }} kg
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            @if($credit->last_latitude && $credit->last_longitude)
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-map-marker-alt text-red-500"></i>
                                    <span>{{ number_format($credit->last_latitude, 4) }}, {{ number_format($credit->last_longitude, 4) }}</span>
                                </div>
                                @if($credit->last_speed_kmph)
                                <div class="text-xs text-gray-500">{{ $credit->last_speed_kmph }} km/h</div>
                                @endif
                            @else
                                <span class="text-gray-400">No GPS</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $credit->last_sensor_update ? \Carbon\Carbon::parse($credit->last_sensor_update)->diffForHumans() : 'Never' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button onclick="viewDeviceDetails('{{ $credit->device_id }}')" 
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Detail
                            </button>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-500">
                            <i class="fas fa-car text-4xl text-gray-300 mb-2"></i>
                            <p>Belum ada kendaraan dengan sensor terpasang</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Device Detail Modal -->
<div id="deviceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Detail Device</h3>
                    <button onclick="closeDeviceModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="deviceDetails">
                    <!-- Device details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart initialization
@if(Auth::user()->role === 'admin' && isset($emissionDashboard['emission_chart_data']))
const ctx = document.getElementById('emissionChart').getContext('2d');
const emissionChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: {!! json_encode(array_column($emissionDashboard['emission_chart_data'], 'date')) !!},
        datasets: [{
            label: 'Emisi Harian (kg)',
            data: {!! json_encode(array_column($emissionDashboard['emission_chart_data'], 'emissions')) !!},
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
@endif

// Auto refresh data every 30 seconds
setInterval(refreshData, 30000);

function refreshData() {
    fetch('/api/emission-data')
        .then(response => response.json())
        .then(data => {
            // Update dashboard stats if admin
            @if(Auth::user()->role === 'admin')
            updateDashboardStats(data);
            @endif
            
            // Update vehicle rows
            updateVehicleData(data);
        })
        .catch(error => {
            console.error('Error refreshing data:', error);
        });
}

function updateDashboardStats(data) {
    // Update stats cards with new data
    // Implementation depends on API response structure
}

function updateVehicleData(data) {
    // Update vehicle table rows with new data
    // Implementation depends on API response structure
}

function viewDeviceDetails(deviceId) {
    fetch(`/api/emission-data?device_id=${deviceId}`)
        .then(response => response.json())
        .then(data => {
            const detailsHtml = `
                <div class="space-y-4">
                    <!-- Header Info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Device ID</p>
                            <p class="font-semibold">${data.device_id}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Kendaraan</p>
                            <p class="font-semibold">${data.nrkb} (${data.vehicle_type})</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Status</p>
                            <p class="font-semibold capitalize">${data.sensor_status}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Sensor Aktif</p>
                            <p class="font-semibold">${data.sensor_status === 'active' ? '✅ Ya' : '❌ Tidak'}</p>
                        </div>
                    </div>
                    
                    <!-- 🔥 AKUMULASI HARIAN -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="font-semibold text-blue-800 mb-3">📊 Akumulasi Harian (${data.daily.date})</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-blue-600">Total CO2e (mg/m³)</p>
                                <p class="font-bold text-blue-800">${data.daily.total_co2e_mg_m3 || 0}</p>
                            </div>
                            <div>
                                <p class="text-sm text-blue-600">Rata-rata (mg/m³)</p>
                                <p class="font-bold text-blue-800">${data.daily.avg_co2e_mg_m3 || 0}</p>
                            </div>
                            <div>
                                <p class="text-sm text-blue-600">Maksimum (mg/m³)</p>
                                <p class="font-bold text-blue-800">${data.daily.max_co2e_mg_m3 || 0}</p>
                            </div>
                            <div>
                                <p class="text-sm text-blue-600">Jumlah Record</p>
                                <p class="font-bold text-blue-800">${data.daily.record_count}</p>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <p class="text-sm text-blue-600">Total Emisi Harian</p>
                            <p class="font-bold text-lg text-blue-800">${data.daily.emissions_kg} kg</p>
                        </div>
                    </div>
                    
                    <!-- 🔥 AKUMULASI BULANAN -->
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <h4 class="font-semibold text-green-800 mb-3">📊 Akumulasi Bulanan (${data.monthly.month}/${data.monthly.year})</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-green-600">Total CO2e (mg/m³)</p>
                                <p class="font-bold text-green-800">${data.monthly.total_co2e_mg_m3 || 0}</p>
                            </div>
                            <div>
                                <p class="text-sm text-green-600">Rata-rata (mg/m³)</p>
                                <p class="font-bold text-green-800">${data.monthly.avg_co2e_mg_m3 || 0}</p>
                            </div>
                            <div>
                                <p class="text-sm text-green-600">Jumlah Record</p>
                                <p class="font-bold text-green-800">${data.monthly.record_count}</p>
                            </div>
                            <div>
                                <p class="text-sm text-green-600">Total Emisi</p>
                                <p class="font-bold text-green-800">${data.monthly.emissions_kg} kg</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 🔥 AKUMULASI TOTAL -->
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                        <h4 class="font-semibold text-purple-800 mb-3">📊 Akumulasi Total (Keseluruhan)</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-purple-600">Total CO2e (mg/m³)</p>
                                <p class="font-bold text-purple-800">${data.total.total_co2e_mg_m3 || 0}</p>
                            </div>
                            <div>
                                <p class="text-sm text-purple-600">Rata-rata (mg/m³)</p>
                                <p class="font-bold text-purple-800">${data.total.avg_co2e_mg_m3 || 0}</p>
                            </div>
                            <div>
                                <p class="text-sm text-purple-600">Total Record</p>
                                <p class="font-bold text-purple-800">${data.total.record_count}</p>
                            </div>
                            <div>
                                <p class="text-sm text-purple-600">Total Emisi</p>
                                <p class="font-bold text-purple-800">${data.total.emissions_kg} kg</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- THRESHOLD & ALERT -->
                    <div class="bg-${data.threshold.is_threshold_exceeded ? 'red' : 'gray'}-50 p-4 rounded-lg border border-${data.threshold.is_threshold_exceeded ? 'red' : 'gray'}-200">
                        <h4 class="font-semibold text-${data.threshold.is_threshold_exceeded ? 'red' : 'gray'}-800 mb-3">⚠️ Status Threshold</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-${data.threshold.is_threshold_exceeded ? 'red' : 'gray'}-600">Batas Emisi Harian</p>
                                <p class="font-bold text-${data.threshold.is_threshold_exceeded ? 'red' : 'gray'}-800">${data.threshold.emission_threshold_kg} kg</p>
                            </div>
                            <div>
                                <p class="text-sm text-${data.threshold.is_threshold_exceeded ? 'red' : 'gray'}-600">Status</p>
                                <p class="font-bold text-${data.threshold.is_threshold_exceeded ? 'red' : 'green'}-800">
                                    ${data.threshold.is_threshold_exceeded ? '❌ MELEBIHI BATAS' : '✅ DALAM BATAS'}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    ${data.location.latitude ? `
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 mb-2">📍 Lokasi Terakhir</p>
                        <p class="font-semibold">${data.location.latitude}, ${data.location.longitude}</p>
                        <p class="text-sm text-gray-600">Kecepatan: ${data.location.speed_kmph || 0} km/h</p>
                    </div>
                    ` : ''}
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600">🕒 Update Terakhir</p>
                        <p class="font-semibold">${data.last_update || 'Never'}</p>
                    </div>
                </div>
            `;
            document.getElementById('deviceDetails').innerHTML = detailsHtml;
            document.getElementById('deviceModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error loading device details:', error);
        });
}

function closeDeviceModal() {
    document.getElementById('deviceModal').classList.add('hidden');
}
</script>
@endsection
