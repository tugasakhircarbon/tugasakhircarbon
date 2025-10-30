@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold">
            @if(Auth::user()->isAdmin())
                Semua Kendaraan
            @else
                Kendaraan Saya
            @endif
        </h2>
        @if(!Auth::user()->isAdmin())
        <a href="{{ route('carbon-credits.create') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-primary">
            <i class="fas fa-plus mr-2" aria-hidden="true"></i> Tambah Kendaraan
        </a>
        @endif
    </div>

    @if($vehicles->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 rounded-lg border border-gray-200" role="table" aria-label="Daftar Kendaraan">
                <thead>
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">No</th>
                        @if(Auth::user()->isAdmin())
                            <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Pemilik</th>
                        @endif
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">NRKB</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Nomor Rangka 5 Digit</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Jenis Kendaraan</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($vehicles as $index => $vehicle)
                    <tr class="hover:bg-gray-50 focus-within:bg-gray-100 transition-colors duration-200" tabindex="0">
                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $vehicles->firstItem() + $index }}</td>
                        @if(Auth::user()->isAdmin())
                            <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $vehicle->owner->name }}</td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $vehicle->nrkb }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $vehicle->nomor_rangka_5digit }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            @if($vehicle->vehicle_type === 'car')
                                Mobil
                            @elseif($vehicle->vehicle_type === 'motorcycle')
                                Motor
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            @switch($vehicle->status)
                                @case('pending')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-semibold">Menunggu Persetujuan</span>
                                    @break
                                @case('approved')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-semibold">Disetujui</span>
                                    @break
                                @case('pending_sale')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-semibold">Menunggu Persetujuan Penjualan</span>
                                    @break
                                @case('available')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs font-semibold">Tersedia</span>
                                    @break
                                @case('rejected')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-red-100 text-red-800 text-xs font-semibold">Ditolak</span>
                                    @break
                                @case('sold')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-200 text-gray-600 text-xs font-semibold">Terjual</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <div class="flex space-x-2">
                                <a href="{{ route('carbon-credits.show', $vehicle->id) }}" title="Lihat Detail" class="inline-flex items-center px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </a>
                                @if(!Auth::user()->isAdmin())
                                    <a href="{{ route('carbon-credits.edit', $vehicle->id) }}" title="Edit" class="inline-flex items-center px-2 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                @endif
                                @if(Auth::user()->isAdmin() && $vehicle->status === 'pending')
                                    <form method="POST" action="{{ route('carbon-credits.approve', $vehicle->id) }}" style="display: inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" title="Setujui" onclick="return confirm('Yakin ingin menyetujui kendaraan ini?')" class="inline-flex items-center px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-700">
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('carbon-credits.reject', $vehicle->id) }}" style="display: inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" title="Tolak" onclick="return confirm('Yakin ingin menolak kendaraan ini?')" class="inline-flex items-center px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-700">
                                            <i class="fas fa-times" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mt-4">
            {{ $vehicles->links() }}
        </div>
    @else
        <div class="text-center py-10">
            <i class="fas fa-car fa-3x text-gray-400 mb-4"></i>
            <h3 class="text-gray-600 text-lg mb-2">Belum ada kendaraan</h3>
            <p class="text-gray-500 mb-4">Mulai dengan menambahkan kendaraan pertama Anda.</p>
            <a href="{{ route('carbon-credits.create') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-primary">
                <i class="fas fa-plus mr-2" aria-hidden="true"></i> Tambah Kendaraan
            </a>
        </div>
    @endif
</div>
@endsection
