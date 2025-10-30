@extends('layouts.app')

@section('header')
Transactions
@endsection

@section('content')
<div class="bg-white p-6 rounded-xl shadow border border-gray-100">
    <h1 class="text-2xl font-bold mb-6">Daftar Transaksi</h1>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" role="table" aria-label="Daftar Transaksi">
            <thead>
                <tr>
                    <th scope="col" class="py-3 px-4 border-b text-left text-sm font-semibold uppercase">ID</th>
                    <th scope="col" class="py-3 px-4 border-b text-left text-sm font-semibold uppercase">Tanggal</th>
                    <th scope="col" class="py-3 px-4 border-b text-left text-sm font-semibold uppercase">Total</th>
                    <th scope="col" class="py-3 px-4 border-b text-left text-sm font-semibold uppercase">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                <tr class="hover:bg-gray-50 focus-within:bg-gray-100 transition-colors duration-200" tabindex="0">
                    <td class="py-2 px-4 border-b text-sm font-medium text-primary">
                        <a href="{{ route('transactions.show', $transaction->id) }}" class="hover:underline">
                            {{ $transaction->transaction_id }}
                        </a>
                    </td>
                    <td class="py-2 px-4 border-b text-sm text-gray-600">
                        {{ $transaction->created_at->format('Y-m-d') }}
                    </td>
                    <td class="py-2 px-4 border-b text-sm text-gray-600">
                        Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}
                    </td>
                    <td class="py-2 px-4 border-b text-sm">
                        @php
                            $statusColors = [
                                'pending' => 'bg-blue-100 text-blue-800',
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
@endsection
