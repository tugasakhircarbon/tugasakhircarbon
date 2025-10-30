@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold mb-6 text-primary">Pembayaran Transaksi</h2>

    <!-- Detail Transaksi -->
    <div class="mb-6 p-4 bg-gray-50 rounded border border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p><strong>ID Transaksi:</strong><br>{{ $transaction->transaction_id }}</p>
                <p><strong>Penjual:</strong><br>{{ $transaction->seller->name }}</p>
                <p><strong>Pembeli:</strong><br>{{ $transaction->buyer->name }}</p>
            </div>
            <div>
                <p><strong>Jumlah:</strong><br>{{ number_format($transaction->amount, 2) }} kg CO₂e</p>
                <p><strong>Harga per Unit:</strong><br>Rp {{ number_format($transaction->price_per_unit, 0, ',', '.') }}</p>
                <p><strong>Total Pembayaran:</strong><br><span class="text-green-600 text-xl font-semibold">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</span></p>
            </div>
        </div>
    </div>

    <!-- Pembayaran Midtrans -->
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-300">
        <h3 class="text-lg font-semibold mb-4 flex items-center space-x-2 text-yellow-700">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <span>Pembayaran Aman dengan Midtrans</span>
        </h3>
        <div class="text-center">
            <p class="mb-4">
                Silakan klik tombol di bawah untuk melanjutkan ke halaman pembayaran yang aman.
            </p>

            <button 
                id="pay-button"
                class="inline-flex items-center px-6 py-3 bg-yellow-500 text-white rounded hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed"
                @if(!$transaction->midtrans_snap_token) disabled @endif>
                <i class="fas fa-credit-card mr-2" aria-hidden="true"></i> Bayar Sekarang
            </button>

            @if(!$transaction->midtrans_snap_token)
                <div class="mt-3 p-3 bg-red-100 text-red-700 rounded flex items-center space-x-2" role="alert">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <span>Token pembayaran tidak tersedia. Silakan hubungi administrator.</span>
                </div>
            @endif

            <div class="mt-4 text-sm text-gray-600 flex items-center justify-center space-x-2">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                <span>Pembayaran aman dengan enkripsi SSL</span>
            </div>
        </div>
    </div>

    <div class="flex justify-end mt-6">
        <a href="{{ route('marketplace') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary">
            <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali ke Marketplace
        </a>
    </div>
</div>
@endsection

@section('scripts')
@if($transaction->midtrans_snap_token)
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key') }}"></script>
<script>
    const payButton = document.querySelector('#pay-button');
    const snapToken = '{{ $transaction->midtrans_snap_token }}';

    payButton.addEventListener('click', function(e) {
        e.preventDefault();

        snap.pay(snapToken, {
            onSuccess: function(result) {
                window.location.href = '{{ route('transactions.show', $transaction->id) }}';
            },
            onPending: function(result) {
                window.location.href = '{{ route('transactions.show', $transaction->id) }}';
            },
            onError: function(result) {
                alert('Pembayaran gagal: ' + result.status_message);
                window.location.href = '{{ route('transactions.show', $transaction->id) }}';
            },
            onClose: function() {
                // Batalkan transaksi atau biarkan user mencoba lagi
            }
        });
    });
</script>
@endif
@endsection
