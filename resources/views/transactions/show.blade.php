@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold mb-6 text-primary">Detail Transaksi</h2>
    <div class="border border-gray-300 rounded-lg p-6 space-y-4">
        <p><strong>Transaksi ID:</strong> <span class="text-gray-700">{{ $transaction->transaction_id }}</span></p>
        <p><strong>Penjual:</strong> <span class="text-gray-700">{{ $transaction->seller->name }}</span></p>
        <p><strong>Pembeli:</strong> <span class="text-gray-700">{{ $transaction->buyer->name }}</span></p>
        <p><strong>Jumlah:</strong> 
            @php
                $totalAmount = $transaction->details->sum('amount');
            @endphp
            <span class="text-gray-700">{{ number_format($totalAmount, 2) }} kg CO₂e</span>
        </p>
        <p><strong>Harga per Unit:</strong> <span class="text-gray-700">Rp {{ number_format($transaction->price_per_unit, 0, ',', '.') }}</span></p>
        <p><strong>Total Pembayaran:</strong> <span class="text-gray-700">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</span></p>
        <p><strong>Status:</strong> 
            @php
                $statusColors = [
                    'pending' => 'bg-blue-100 text-yellow-800',
                    'success' => 'bg-green-100 text-green-800',
                    'failed' => 'bg-red-100 text-red-800',
                    'expired' => 'bg-yellow-100 text-yellow-800',
                ];
                $colorClass = $statusColors[$transaction->status] ?? 'bg-gray-200 text-gray-700';
            @endphp
            <span class="px-2 py-1 text-xs rounded-full {{ $colorClass }}">{{ ucfirst($transaction->status) }}</span>
        </p>
        <p><strong>Metode Pembayaran:</strong> <span class="text-gray-700">{{ $transaction->payment_method ?? '-' }}</span></p>
        <p><strong>Tanggal Pembayaran:</strong> <span class="text-gray-700">{{ $transaction->paid_at ? $transaction->paid_at->format('d-m-Y H:i') : '-' }}</span></p>
    </div>
    @if($transaction->status === 'pending')
        <div class="mt-6">
            <a href="{{ route('transactions.payment', $transaction->id) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                <i class="fas fa-credit-card mr-2" aria-hidden="true"></i> Lanjutkan Pembayaran
            </a>
        </div>
    @endif
    <div class="mt-6">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600">
            <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali ke Daftar Transaksi
        </a>
    </div>
</div>
@endsection
