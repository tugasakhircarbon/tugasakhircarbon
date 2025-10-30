@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold">
            @if(Auth::user()->isAdmin())
                Semua Kuota Karbon
            @else
                Kuota Karbon Saya
            @endif
        </h2>
    </div>


    @if($carbonCredits->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 rounded-lg border border-gray-200" role="table" aria-label="Daftar Kuota Karbon">
                <thead>
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">No</th>
                        @if(Auth::user()->isAdmin())
                            <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Pemilik</th>
                        @endif
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">NRKB Kendaraan</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Emisi Harian (kg)</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Kuota Karbon (kg CO₂e)</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Harga/Unit</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-sm font-semibold uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($carbonCredits as $index => $credit)
                    <tr class="hover:bg-gray-50 focus-within:bg-gray-100 transition-colors duration-200" tabindex="0">
                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $carbonCredits->firstItem() + $index }}</td>
                        @if(Auth::user()->isAdmin())
                            <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $credit->owner->name }}</td>
                        @endif
                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $credit->nrkb ?? '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ number_format($credit->daily_emissions_kg ?? 0, 2) }} kg</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ number_format($credit->effective_quota, 2) }} <span class="text-xs text-gray-500">kg CO₂e</span></td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">Rp {{ number_format($credit->price_per_unit, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            @switch($credit->status)
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
                                <a href="{{ route('carbon-credits.show', $credit->id) }}" title="Lihat Detail" class="inline-flex items-center px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </a>
                                @if((!Auth::user()->isAdmin() && $credit->owner_id === Auth::id()) || Auth::user()->isAdmin())
                                    <a href="{{ route('carbon-credits.edit', $credit->id) }}" title="Edit" class="inline-flex items-center px-2 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                @endif
                                @if(!Auth::user()->isAdmin() && $credit->owner_id === Auth::id() && $credit->status === 'available')
                                    <button type="button" title="Ajukan Penjualan" onclick="if(confirm('Yakin ingin mengajukan kuota karbon ini untuk dijual?')) { window.location.href='{{ route('carbon-credits.request-sale', $credit->id) }}' }" class="inline-flex items-center px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-600">
                                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                    </button>
                                @endif
                                @if(Auth::user()->isAdmin())
                                    @if($credit->status === 'pending')
                                        <form method="POST" action="{{ route('carbon-credits.approve', $credit->id) }}" style="display: inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" title="Setujui" onclick="return confirm('Yakin ingin menyetujui kuota karbon ini?')" class="inline-flex items-center px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-700">
                                                <i class="fas fa-check" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('carbon-credits.reject', $credit->id) }}" style="display: inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" title="Tolak" onclick="return confirm('Yakin ingin menolak kuota karbon ini?')" class="inline-flex items-center px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-700">
                                                <i class="fas fa-times" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if($credit->status === 'pending_sale')
                                        <form method="POST" action="{{ route('carbon-credits.approve-sale-request', $credit->id) }}" style="display: inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" title="Setujui" onclick="return confirm('Yakin ingin menyetujui penjualan kuota karbon ini?')" class="inline-flex items-center px-2 py-1 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-700">
                                                <i class="fas fa-check" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('carbon-credits.reject-sale-request', $credit->id) }}" style="display: inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" title="Tolak" onclick="return confirm('Yakin ingin menolak kuota karbon ini?')" class="inline-flex items-center px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-700">
                                                <i class="fas fa-times" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
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
            {{ $carbonCredits->links() }}
        </div>
    @else
        <div class="text-center py-10">
            <i class="fas fa-leaf fa-3x text-gray-400 mb-4"></i>
            <h3 class="text-gray-600 text-lg mb-2">Belum ada kuota karbon</h3>
            <p class="text-gray-500 mb-4">Mulai dengan menambahkan kendaraan pertama Anda.</p>
            <a href="{{ route('carbon-credits.create') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-primary">
                <i class="fas fa-plus mr-2" aria-hidden="true"></i> Tambah Kendaraan
            </a>
        </div>
    @endif
</div>
@endsection
