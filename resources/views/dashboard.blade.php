@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold flex items-center space-x-3 text-primary">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </h1>
        <div class="flex items-center space-x-4">
            <a href="{{ route('emission.monitoring') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-chart-line"></i>
                <span>Monitoring Emisi</span>
            </a>
            <div class="text-gray-600">
                Selamat datang, {{ Auth::user()->name }}!
                @if(Auth::user()->isAdmin())
                    <span class="inline-block bg-yellow-400 text-yellow-900 text-xs font-semibold px-2 py-1 rounded ml-2">Administrator</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Emission Monitoring Summary -->
    @if(isset($emissionStats) && $emissionStats['total_devices'] > 0)
    <div class="bg-gradient-to-r from-green-50 to-blue-50 p-6 rounded-xl shadow border border-green-100 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center space-x-2">
                <i class="fas fa-leaf text-green-600"></i>
                <span>Monitoring Emisi Real-time</span>
            </h3>
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-sm text-gray-600">Live Data</span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Kendaraan</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $emissionStats['total_devices'] }}</p>
                    </div>
                    <i class="fas fa-car text-blue-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Sensor Aktif</p>
                        <p class="text-2xl font-bold text-green-600">{{ $emissionStats['active_devices'] }}</p>
                    </div>
                    <i class="fas fa-wifi text-green-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Emisi Hari Ini</p>
                        <p class="text-2xl font-bold text-orange-600">{{ number_format($emissionStats['today_emissions'], 2) }} kg</p>
                    </div>
                    <i class="fas fa-smog text-orange-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Alert Aktif</p>
                        <p class="text-2xl font-bold {{ $emissionStats['alerts_count'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $emissionStats['alerts_count'] }}
                        </p>
                    </div>
                    <i class="fas fa-exclamation-triangle {{ $emissionStats['alerts_count'] > 0 ? 'text-red-500' : 'text-green-500' }} text-xl"></i>
                </div>
            </div>
        </div>

        @if(count($emissionStats['vehicles']) > 0)
        <div class="bg-white p-4 rounded-lg shadow-sm">
            <h4 class="font-semibold text-gray-800 mb-3">Status Kendaraan</h4>
            <div class="space-y-2">
                @foreach($emissionStats['vehicles'] as $vehicle)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <i class="fas {{ $vehicle['vehicle_type'] === 'motorcycle' ? 'fa-motorcycle' : 'fa-car' }} text-blue-500"></i>
                        <div>
                            <p class="font-medium text-gray-800">{{ $vehicle['nrkb'] }}</p>
                            <p class="text-sm text-gray-600">{{ ucfirst($vehicle['vehicle_type']) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-center">
                            <p class="text-xs text-gray-500">CO2e PPM</p>
                            {{-- <p class="font-semibold {{ $vehicle['current_co2e_mg_m3'] > 100 ? 'text-red-600' : 'text-green-600' }}"> {{ number_format($vehicle['current_co2e_mg_m3']) }}
                            </p> --}}
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-gray-500">Emisi Harian</p>
                            <p class="font-semibold text-orange-600">{{ number_format($vehicle['daily_emissions_kg'], 2) }} kg</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded-full {{ $vehicle['sensor_status'] === 'active' ? 'bg-green-500' : ($vehicle['sensor_status'] === 'error' ? 'bg-red-500' : 'bg-gray-400') }}"></div>
                            <span class="text-sm capitalize {{ $vehicle['sensor_status'] === 'active' ? 'text-green-600' : ($vehicle['sensor_status'] === 'error' ? 'text-red-600' : 'text-gray-600') }}">
                                {{ $vehicle['sensor_status'] }}
                            </span>
                            @if($vehicle['has_alert'])
                                <i class="fas fa-exclamation-triangle text-red-500 text-sm"></i>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- Statistik Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <x-stats-card
            title="Pencairan Tertunda"
            :value="$stats['pending_payouts']"
            icon-class="fas fa-clock"
            icon-bg-class="bg-yellow-100"
            icon-text-class="text-yellow-500"
        />
        @foreach($vehicleCarbonCredits as $vehicle)
        <x-stats-card
            :title="$vehicle['nrkb']"
            :value="number_format($vehicle['effective_quota'], 2) . ' kg'"
            :icon-class="$vehicle['vehicle_type'] === 'motorcycle' ? 'fas fa-motorcycle' : 'fas fa-car'"
            icon-bg-class="bg-blue-100"
            icon-text-class="text-blue-500"
        />
        @endforeach
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-dark">Recent Transactions</h3>
            <a href="{{ route('transactions.index') }}" class="text-sm text-primary hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($transactions->take(5) as $transaction)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-primary">
                            <a href="{{ route('transactions.show', $transaction->id) }}">
                                {{ $transaction->transaction_id }}
                            </a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ $transaction->created_at->format('Y-m-d') }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-blue-100 text-yellow-800',
                                    'success' => 'bg-green-100 text-green-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                    'expired' => 'bg-yellow-100 text-yellow-800',
                                ];
                                $statusLabels = [
                                    'pending' => 'Tertunda',
                                    'success' => 'Selesai',
                                    'failed' => 'Dibatalkan',
                                    'expired' => 'Dibatalkan',
                                ];
                                $colorClass = $statusColors[$transaction->status] ?? 'bg-gray-200 text-gray-700';
                                $label = $statusLabels[$transaction->status] ?? ucfirst($transaction->status);
                            @endphp
                            <span class="px-2 py-1 text-xs rounded-full {{ $colorClass }}">{{ $label }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-500">Tidak ada transaksi ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
