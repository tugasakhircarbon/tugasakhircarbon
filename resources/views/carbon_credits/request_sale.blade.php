{{-- resources/views/carbon_credits/request_sale.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold mb-6 text-primary">Pengajuan Penjualan Kuota Karbon</h2>

    {{-- Info Kuota Karbon --}}
    <div class="mb-6 p-4 bg-blue-100 rounded text-blue-800">
        <h5 class="font-semibold mb-2 flex items-center space-x-2">
            <i class="fas fa-leaf" aria-hidden="true"></i>
            <span>{{ $carbonCredit->title }}</span>
        </h5>
        <p><strong>Jumlah Tersedia:</strong> {{ number_format($carbonCredit->effective_quota, 2) }} kg CO₂e</p>
        <p><strong>Harga Saat Ini:</strong> Rp {{ number_format($carbonCredit->price_per_unit, 0, ',', '.') }}/kg CO₂e</p>
    </div>

    <form method="POST" action="{{ route('carbon-credits.submit-sale-request', $carbonCredit) }}" novalidate>
        @csrf
        @method('PATCH')
        
        <div class="mb-4">
            <label for="quantity_to_sell" class="block font-semibold mb-1">Jumlah yang Ingin Dijual (kg CO₂e) <span class="text-red-600">*</span></label>
            <input type="number" 
                   class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('quantity_to_sell') border-red-500 @enderror" 
                   id="quantity_to_sell" 
                   name="quantity_to_sell"
                   value="{{ old('quantity_to_sell') }}"
                   step="0.01"
                   max="{{ $carbonCredit->amount }}"
                   required
                   aria-describedby="quantityHelp"
                   aria-invalid="@error('quantity_to_sell') true @else false @enderror">
            @error('quantity_to_sell')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label class="block font-semibold mb-1">Harga Jual per Unit (Rp)</label>
            <div class="w-full rounded border border-gray-300 px-3 py-2">
                Rp 100
            </div>
        </div>

        {{-- Tambahkan display total estimasi --}}
        <div class="mb-4 p-4 bg-gray-100 rounded text-gray-800" role="region" aria-live="polite" aria-atomic="true">
            <strong>Total Estimasi Nilai Penjualan:</strong>
            <span id="total-display">Rp 0</span>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('carbon-credits.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary">
                <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600">
                <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i> Kirim Pengajuan
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity_to_sell');
    const totalDisplay = document.getElementById('total-display');
    const fixedPrice = 100; // Harga tetap 100
    
    function updateEstimatedValue() {
        const quantity = parseFloat(quantityInput.value) || 0;
        const total = quantity * fixedPrice;
        
        if (totalDisplay) {
            totalDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
        }
    }
    
    if (quantityInput) {
        quantityInput.addEventListener('input', updateEstimatedValue);
        
        // Update saat halaman dimuat
        updateEstimatedValue();
    }
});
</script>
@endsection
