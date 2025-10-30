@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold mb-6 text-primary">Beli Kuota Karbon</h2>

    @if($isAdmin)
        <!-- Admin View: Detail with Purchase Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h3 class="text-lg font-semibold mb-3">Detail Kuota Karbon</h3>
                <div class="border-t pt-3 mt-3">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <div class="text-gray-600">Jumlah Dibeli</div>
                            <div class="text-blue-600 font-semibold text-lg">{{ number_format($carbonCredit->quantity_to_sell, 2) }} kg CO₂e</div>
                        </div>
                        <div>
                            <div class="text-gray-600">Harga per Unit</div>
                            <div class="text-green-600 font-semibold text-lg">Rp {{ number_format($carbonCredit->price_per_unit, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-blue-100 rounded">
                        <div class="text-gray-700 text-sm">Total Pembayaran</div>
                        <div class="text-red-600 font-bold text-xl">Rp {{ number_format($carbonCredit->quantity_to_sell * $carbonCredit->price_per_unit, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h3 class="text-lg font-semibold mb-3">Informasi Penjual</h3>
                <p class="text-gray-700 mb-1"><strong>Nama:</strong> {{ $carbonCredit->owner->name }}</p>
                <p class="text-gray-700 mb-1"><strong>Email:</strong> {{ $carbonCredit->owner->email }}</p>
                <p class="text-gray-700 mb-4"><strong>Bergabung:</strong> {{ $carbonCredit->owner->created_at->format('d M Y') }}</p>
                <div class="mb-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm font-medium">
                        <i class="fas fa-check-circle mr-1" aria-hidden="true"></i> Terverifikasi
                    </span>
                </div>
                <div class="border-t pt-3 mt-3">
                    <div class="bg-yellow-100 p-3 rounded border border-yellow-300">
                        <p class="text-yellow-800 text-sm">
                            <i class="fas fa-info-circle mr-1" aria-hidden="true"></i>
                            <strong>Catatan Admin:</strong> Anda akan secara otomatis membeli seluruh kuota karbon yang tersedia untuk dijual.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('transactions.store', $carbonCredit->id) }}" method="POST" id="purchaseForm">
            @csrf
            <div class="flex justify-end space-x-4">
                <a href="{{ route('admin.marketplace') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary">
                    <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali
                </a>
                <button type="submit" class="inline-flex items-center px-6 py-3 bg-yellow-500 text-white rounded hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-600 text-lg font-semibold">
                    <i class="fas fa-credit-card mr-2" aria-hidden="true"></i> Lanjut ke Pembayaran
                </button>
            </div>
        </form>
    @else
        <!-- Regular User View: Original Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h3 class="text-lg font-semibold mb-3">Detail Kuota Karbon</h3>
                <h4 class="text-xl font-bold mb-2">{{ $carbonCredit->project_name }}</h4>
                <p class="text-gray-700 mb-1"><strong>Jenis:</strong> {{ $carbonCredit->type }}</p>
                <p class="text-gray-700 mb-1"><strong>Lokasi:</strong> {{ $carbonCredit->location }}</p>
                <p class="text-gray-700 mb-1"><strong>Tahun:</strong> {{ $carbonCredit->year }}</p>
                <p class="text-gray-700 mb-4"><strong>Sertifikat:</strong> {{ $carbonCredit->certification_standard }}</p>
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

            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h3 class="text-lg font-semibold mb-3">Informasi Penjual</h3>
                <p class="text-gray-700 mb-1"><strong>Nama:</strong> {{ $carbonCredit->owner->name }}</p>
                <p class="text-gray-700 mb-1"><strong>Email:</strong> {{ $carbonCredit->owner->email }}</p>
                <p class="text-gray-700 mb-4"><strong>Bergabung:</strong> {{ $carbonCredit->owner->created_at->format('d M Y') }}</p>
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm font-medium">
                        <i class="fas fa-check-circle mr-1" aria-hidden="true"></i> Terverifikasi
                    </span>
                </div>
            </div>
        </div>

        <form action="{{ route('transactions.store', $carbonCredit->id) }}" method="POST" id="purchaseForm" novalidate>
            @csrf

            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-300">
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
                               class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 @error('quantity_to_sell') border-red-500 @enderror" 
                               id="quantity_to_sell" 
                               name="quantity_to_sell" 
                               step="0.01" 
                               min="0.01" 
                               max="{{ $carbonCredit->quantity_to_sell }}" 
                               value="{{ old('quantity_to_sell') }}"
                               placeholder="Masukkan jumlah..."
                               required
                               aria-describedby="quantityHelp"
                               aria-invalid="@error('quantity_to_sell') true @else false @enderror">
                        <p id="quantityHelp" class="text-sm text-gray-600 mt-1">
                            Maksimal: {{ number_format($carbonCredit->quantity_to_sell, 2) }} kg CO₂e
                        </p>
                        @error('quantity_to_sell')
                            <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block font-semibold mb-1" for="totalPrice">
                            Total Harga
                        </label>
                        <div class="flex items-center border border-gray-300 rounded px-3 py-2 bg-white">
                            <span class="text-gray-700 mr-2">Rp</span>
                            <input type="text" class="w-full bg-transparent focus:outline-none" id="totalPrice" readonly value="0" aria-live="polite" />
                        </div>
                        <p class="text-sm text-green-600 mt-1">Harga akan dihitung otomatis</p>
                    </div>
                </div>

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
                    <a href="{{ route('marketplace') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary">
                        <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed" id="buyButton" disabled>
                        <i class="fas fa-credit-card mr-2" aria-hidden="true"></i> Lanjut ke Pembayaran
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isAdmin = {{ $isAdmin ? 'true' : 'false' }};
    
    // Only run calculation logic for regular users
    if (!isAdmin) {
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
                amountInput.classList.add('is-invalid');
                buyButton.disabled = true;
            } else {
                amountInput.classList.remove('is-invalid');
            }
        }

        amountInput.addEventListener('input', calculateTotal);
        amountInput.addEventListener('change', calculateTotal);
        
        // Initial calculation
        calculateTotal();
    }
    // For admin users, the button is already enabled and no calculation is needed
});
</script>
@endsection
