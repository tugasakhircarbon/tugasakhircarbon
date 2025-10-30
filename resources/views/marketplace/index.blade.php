@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold mb-6 flex items-center space-x-3 text-primary">
        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
        <span>Beli Kuota Karbon</span>
    </h2>

    <!-- Detail Kuota Karbon -->
    {{-- <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="border border-green-500 rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3">{{ $carbonCredit->project_name }}</h3>
            <p class="mb-1"><strong>Jenis:</strong> {{ $carbonCredit->type }}</p>
            <p class="mb-1"><strong>Lokasi:</strong> {{ $carbonCredit->location }}</p>
            <p class="mb-1"><strong>Tahun:</strong> {{ $carbonCredit->year }}</p>
            <p class="mb-4"><strong>Sertifikat:</strong> {{ $carbonCredit->certification_standard }}</p>
            <div class="flex justify-between text-sm text-gray-600">
                <div>
                    <div>Harga per Unit</div>
                    <div class="text-green-600 font-semibold text-lg">Rp {{ number_format($carbonCredit->price_per_unit, 0, ',', '.') }}</div>
                </div>
                <div>
                    <div>Tersedia</div>
                    <div class="text-blue-600 font-semibold text-lg">{{ number_format($carbonCredit->quantity_to_sell, 2) }} kg CO₂e</div>
                </div>
            </div>
        </div>

        <div class="border border-blue-500 rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3">Informasi Penjual</h3>
            <p class="mb-1"><strong>Nama:</strong> {{ $carbonCredit->owner->name }}</p>
            <p class="mb-1"><strong>Email:</strong> {{ $carbonCredit->owner->email }}</p>
            <p class="mb-4"><strong>Bergabung:</strong> {{ $carbonCredit->owner->created_at->format('d M Y') }}</p>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm font-medium">
                    <i class="fas fa-check-circle mr-1" aria-hidden="true"></i> Terverifikasi
                </span>
            </div>
        </div>
    </div> --}}

    <!-- Form Pembelian -->
    <form action="{{ route('transactions.store', $carbonCredit->id) }}" method="POST" id="purchaseForm" class="space-y-6" aria-label="Form Pembelian Kuota Karbon">
        @csrf
        <div class="border border-yellow-400 rounded-lg p-4 bg-yellow-50">
            <h3 class="text-lg font-semibold mb-4 flex items-center space-x-2 text-yellow-700">
                <i class="fas fa-calculator" aria-hidden="true"></i>
                <span>Form Pembelian</span>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="quantity_to_sell" class="block font-semibold mb-1">
                        Jumlah yang ingin dibeli (kg CO₂e)
                    </label>
                    <input type="number"
                           id="quantity_to_sell"
                           name="quantity_to_sell"
                           step="0.01"
                           min="0.01"
                           max="{{ $carbonCredit->quantity_to_sell }}"
                           value="{{ old('quantity_to_sell') }}"
                           placeholder="Masukkan jumlah..."
                           required
                           class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('quantity_to_sell') border-red-500 @enderror"
                           aria-describedby="quantityHelp"
                           aria-invalid="@error('quantity_to_sell') true @else false @enderror" />
                    <p id="quantityHelp" class="text-sm text-gray-600 mt-1">
                        Maksimal: {{ number_format($carbonCredit->quantity_to_sell, 2) }} kg CO₂e
                    </p>
                    @error('quantity_to_sell')
                        <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="vehicle_id" class="block font-semibold mb-1">Pilih Kendaraan</label>
                    <select id="vehicle_id" name="vehicle_id" required class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('vehicle_id') border-red-500 @enderror" aria-invalid="@error('vehicle_id') true @else false @enderror">
                        <option value="" disabled selected>Pilih kendaraan...</option>
                        @foreach(Auth::user()->vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->nrkb }} - {{ $vehicle->nomor_rangka_5digit }} ({{ ucfirst($vehicle->vehicle_type) }})
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block font-semibold mb-1" for="totalPrice">Total Harga</label>
                    <div class="flex items-center border border-gray-300 rounded px-3 py-2 bg-white">
                        <span class="text-gray-700 mr-2">Rp</span>
                        <input type="text" id="totalPrice" readonly value="0" class="w-full bg-transparent focus:outline-none" aria-live="polite" />
                    </div>
                    <p class="text-sm text-green-600 mt-1">Harga akan dihitung otomatis</p>
                </div>
            </div>

            <!-- Ringkasan Pembelian -->
            <div id="purchaseSummary" class="mt-4 p-4 bg-blue-100 rounded text-blue-800 hidden" role="region" aria-live="polite" aria-atomic="true">
                <h4 class="font-semibold mb-2 flex items-center space-x-2">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <span>Ringkasan Pembelian:</span>
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div><strong>Jumlah:</strong> <span id="summaryAmount">0</span> kg CO₂e</div>
                    <div><strong>Harga per Unit:</strong> Rp {{ number_format($carbonCredit->price_per_unit, 0, ',', '.') }}</div>
                    <div><strong>Total:</strong> Rp <span id="summaryTotal">0</span></div>
                </div>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary">
                    <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali
                </a>
                <button type="submit" id="buyButton" disabled class="inline-flex items-center px-4 py-2 bg-primary text-white rounded hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-green-600">
                    <i class="fas fa-credit-card mr-2" aria-hidden="true"></i> Lanjut ke Pembayaran
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('quantity_to_sell');
    const totalPriceInput = document.getElementById('totalPrice');
    const buyButton = document.getElementById('buyButton');
    const purchaseSummary = document.getElementById('purchaseSummary');
    const summaryAmount = document.getElementById('summaryAmount');
    const summaryTotal = document.getElementById('summaryTotal');
    
    const pricePerUnit = {{ $carbonCredit->price_per_unit }};
    const maxAmount = {{ $carbonCredit->quantity_to_sell }};

    function calculateTotal() {
        const amount = parseFloat(amountInput.value) || 0;
        const total = amount * pricePerUnit;
        
        // Update total price
        totalPriceInput.value = total.toLocaleString('id-ID');
        
        // Update summary
        if (amount > 0) {
            summaryAmount.textContent = amount.toFixed(2);
            summaryTotal.textContent = total.toLocaleString('id-ID');
            purchaseSummary.classList.remove('hidden');
            buyButton.disabled = false;
        } else {
            purchaseSummary.classList.add('hidden');
            buyButton.disabled = true;
        }
        
        // Validate amount
        if (amount > maxAmount) {
            amountInput.classList.add('border-red-500');
            buyButton.disabled = true;
        } else {
            amountInput.classList.remove('border-red-500');
        }
    }

    amountInput.addEventListener('input', calculateTotal);
    amountInput.addEventListener('change', calculateTotal);
    
    // Initial calculation
    calculateTotal();
});
</script>
