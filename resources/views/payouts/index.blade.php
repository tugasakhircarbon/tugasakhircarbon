@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    @if(Auth::user()->role == 'admin')
        <h1 class="text-2xl font-bold mb-4">Manajemen Payout Admin</h1>
    @else
        <h1 class="text-2xl font-bold mb-4">Payout Saya</h1>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300 rounded-lg" role="table" aria-label="Daftar Payout">
            <thead>
                <tr>
                    <th scope="col" class="py-2 px-4 border-b text-left text-sm font-semibold uppercase">ID Payout</th>
                    @if(Auth::user()->role == 'admin')
                    <th scope="col" class="py-2 px-4 border-b text-left text-sm font-semibold uppercase">User</th>
                    @endif
                    <th scope="col" class="py-2 px-4 border-b text-left text-sm font-semibold uppercase">Jumlah</th>
                    <th scope="col" class="py-2 px-4 border-b text-left text-sm font-semibold uppercase">Status</th>
                    <th scope="col" class="py-2 px-4 border-b text-left text-sm font-semibold uppercase">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payouts as $payout)
                <tr class="hover:bg-gray-50 focus-within:bg-gray-100 transition-colors duration-200" tabindex="0">
                    <td class="py-2 px-4 border-b">{{ $payout->payout_id }}</td>
                    @if(Auth::user()->role == 'admin')
                    <td class="py-2 px-4 border-b">{{ $payout->user->name ?? 'N/A' }}</td>
                    @endif
                    <td class="py-2 px-4 border-b">Rp {{ number_format($payout->net_amount, 2, ',', '.') }}</td>
                    <td class="py-2 px-4 border-b capitalize">
                        @php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'created' => 'bg-blue-100 text-blue-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                            ];
                            $colorClass = $statusColors[$payout->status] ?? 'bg-gray-200 text-gray-700';
                        @endphp
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $colorClass }}">{{ ucfirst($payout->status) }}</span>
                    </td>
                    <td class="py-2 px-4 border-b space-x-2">
                        @if($payout->status === 'pending')
                        <form action="{{ route('payouts.create', $payout->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600" aria-label="Buat payout {{ $payout->payout_id }}">
                                <i class="fas fa-plus mr-1" aria-hidden="true"></i> Buat
                            </button>
                        </form>
                        @else
                        <span class="text-gray-500">Tidak ada aksi</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $payouts->links() }}
    </div>
</div>
@endsection
