@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold flex items-center space-x-3 text-primary">
            <i class="fas fa-store" aria-hidden="true"></i>
            <span>Marketplace Admin</span>
        </h1>
    </div>

    <!-- Kuota Karbon Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($carbonCredits as $credit)
        <div class="bg-white rounded-lg shadow p-6 flex flex-col justify-between hover:shadow-lg transition-shadow duration-300 focus-within:ring-2 focus-within:ring-primary" tabindex="0" role="article" aria-label="Kuota Karbon {{ $credit->title }}">
            <div>
                <div class="flex justify-between items-center mb-3">
                    <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs font-semibold">Tersedia</span>
                </div>
                <p class="text-sm text-gray-500 mb-1"><i class="fas fa-user mr-1" aria-hidden="true"></i> Penjual: {{ $credit->owner->name }}</p>
                {{-- <p class="text-sm text-gray-500 mb-1"><i class="fas fa-map-marker-alt mr-1" aria-hidden="true"></i> Lokasi: {{ $credit->project_location }}</p> --}}
                <div class="flex justify-between text-sm text-gray-600 mt-4">
                    <div>
                        <p><i class="fas fa-weight mr-1" aria-hidden="true"></i> Tersedia:</p>
                        <p class="font-semibold text-green-600">{{ number_format($credit->quantity_to_sell, 2) }} kg CO₂e</p>
                    </div>
                    <div>
                        <p><i class="fas fa-tag mr-1" aria-hidden="true"></i> Harga:</p>
                        <p class="font-semibold text-primary">Rp {{ number_format($credit->price_per_unit, 0, ',', '.') }}/kg CO₂e</p>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex space-x-4">
                <a href="{{ route('marketplace.show', $credit->id) }}" class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-blue-500 text-blue-600 rounded hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600" aria-label="Detail kuota karbon {{ $credit->title }}">
                    <i class="fas fa-eye mr-2" aria-hidden="true"></i> Detail
                </a>
                <a href="{{ route('transactions.create', $credit->id) }}" class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600" aria-label="Beli kuota karbon {{ $credit->title }}">
                    <i class="fas fa-shopping-cart mr-2" aria-hidden="true"></i> Beli
                </a>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-10">
            <i class="fas fa-search fa-3x text-gray-400 mb-4" aria-hidden="true"></i>
            <h3 class="text-gray-600 text-lg mb-2">Tidak ada kuota karbon yang ditemukan</h3>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($carbonCredits->hasPages())
    <div class="flex justify-center mt-6">
        {{ $carbonCredits->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection
