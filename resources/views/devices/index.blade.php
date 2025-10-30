@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold flex items-center space-x-3 text-primary">
            <i class="fas fa-microchip"></i>
            <span>Manajemen Device Sensor</span>
        </h1>
        <div class="text-gray-600">
            <i class="fas fa-info-circle"></i>
            Daftarkan device sensor untuk monitoring emisi real-time
        </div>
    </div>

    <!-- Status Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        @php
            $totalVehicles = $carbonCredits->count();
            $withDevice = $carbonCredits->where('device_id', '!=', null)->count();
            $activeDevices = $carbonCredits->where('sensor_status', 'active')->count();
            $withoutDevice = $totalVehicles - $withDevice;
        @endphp
        
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Kendaraan</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $totalVehicles }}</p>
                </div>
                <i class="fas fa-car text-blue-500 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Dengan Device</p>
                    <p class="text-2xl font-bold text-green-600">{{ $withDevice }}</p>
                </div>
                <i class="fas fa-microchip text-green-500 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Device Aktif</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $activeDevices }}</p>
                </div>
                <i class="fas fa-wifi text-blue-500 text-2xl"></i>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Belum Ada Device</p>
                    <p class="text-2xl font-bold text-orange-600">{{ $withoutDevice }}</p>
                </div>
                <i class="fas fa-exclamation-triangle text-orange-500 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Vehicle List -->
    <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Daftar Kendaraan</h3>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Aktif</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                    <span class="text-sm text-gray-600">Terdaftar</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
                    <span class="text-sm text-gray-600">Belum Ada Device</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kendaraan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pemilik</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Threshold</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terdaftar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($carbonCredits as $credit)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center space-x-3">
                                <i class="fas {{ $credit->vehicle_type === 'motorcycle' ? 'fa-motorcycle' : 'fa-car' }} text-blue-500"></i>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $credit->nrkb }}</p>
                                    <p class="text-sm text-gray-600">{{ ucfirst($credit->vehicle_type) }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>
                                <p class="font-medium text-gray-800">{{ $credit->owner->name }}</p>
                                <p class="text-sm text-gray-600">{{ $credit->owner->email }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($credit->device_id)
                                <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">{{ $credit->device_id }}</span>
                            @else
                                <span class="text-gray-400 text-sm">Belum terdaftar</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($credit->device_id)
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
                                        {{ $credit->sensor_status ?? 'inactive' }}
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-400 text-sm">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $credit->emission_threshold_kg ? number_format($credit->emission_threshold_kg, 1) . ' kg/hari' : '-' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $credit->device_registered_at ? $credit->device_registered_at->format('d/m/Y') : '-' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="flex items-center space-x-2">
                                @if($credit->device_id)
                                    <!-- Device sudah terdaftar -->
                                    <a href="{{ route('devices.show', $credit) }}" 
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Detail
                                    </a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('devices.edit', $credit) }}" 
                                       class="text-green-600 hover:text-green-800 text-sm font-medium">
                                        Edit
                                    </a>
                                    <span class="text-gray-300">|</span>
                                    <button onclick="generateQrCode('{{ $credit->id }}')" 
                                            class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                                        QR Code
                                    </button>
                                @else
                                    <!-- Device belum terdaftar -->
                                    <a href="{{ route('devices.create', $credit) }}" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium">
                                        Daftarkan Device
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">
                            <i class="fas fa-car text-4xl text-gray-300 mb-2"></i>
                            <p>Belum ada kendaraan terdaftar</p>
                            <a href="{{ route('carbon-credits.create') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                Daftarkan kendaraan pertama
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">QR Code Setup Device</h3>
                    <button onclick="closeQrModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="qrContent" class="text-center">
                    <!-- QR code will be generated here -->
                </div>
                <div class="mt-4 text-sm text-gray-600">
                    <p><strong>Instruksi:</strong></p>
                    <ol class="list-decimal list-inside space-y-1 mt-2">
                        <li>Scan QR code dengan device sensor</li>
                        <li>Device akan otomatis terkonfigurasi</li>
                        <li>Pasang device pada kendaraan</li>
                        <li>Tunggu status berubah menjadi "active"</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
function generateQrCode(creditId) {
    fetch(`/devices/${creditId}/qr-code`)
        .then(response => response.json())
        .then(data => {
            const qrContent = document.getElementById('qrContent');
            qrContent.innerHTML = '<canvas id="qrCanvas"></canvas>';
            
            const canvas = document.getElementById('qrCanvas');
            QRCode.toCanvas(canvas, data.setup_url, {
                width: 200,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            }, function (error) {
                if (error) {
                    console.error(error);
                    qrContent.innerHTML = '<p class="text-red-600">Error generating QR code</p>';
                } else {
                    const setupUrl = document.createElement('p');
                    setupUrl.className = 'mt-3 text-xs text-gray-500 break-all';
                    setupUrl.textContent = data.setup_url;
                    qrContent.appendChild(setupUrl);
                }
            });
            
            document.getElementById('qrModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error generating QR code');
        });
}

function closeQrModal() {
    document.getElementById('qrModal').classList.add('hidden');
}
</script>
@endsection
