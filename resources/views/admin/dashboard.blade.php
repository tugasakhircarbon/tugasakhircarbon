@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold flex items-center space-x-3 text-primary">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </h1>
        <div class="text-gray-600">
            Selamat datang, {{ Auth::user()->name }}!
            @if(Auth::user()->isAdmin())
                <span class="inline-block bg-yellow-400 text-yellow-900 text-xs font-semibold px-2 py-1 rounded ml-2">Administrator</span>
            @endif
        </div>
    </div>

    @if(Auth::user()->isAdmin())
    <!-- Admin Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-gray-700 text-white rounded-xl shadow p-6 flex items-center justify-between">
            <div>
                <h6 class="text-sm font-semibold">Total Kuota Karbon</h6>
                <h2 class="text-3xl font-bold">{{ $stats['my_carbon_credits'] }}</h2>
            </div>
            <i class="fas fa-globe fa-3x opacity-75"></i>
        </div>

        <div class="bg-red-600 text-white rounded-xl shadow p-6 flex items-center justify-between">
            <div>
                <h6 class="text-sm font-semibold">Menunggu Persetujuan</h6>
                <h2 class="text-3xl font-bold">{{ $stats['pending_approvals'] }}</h2>
            </div>
            <i class="fas fa-hourglass-half fa-3x opacity-75"></i>
        </div>

        <div class="bg-gray-900 text-white rounded-xl shadow p-6 flex items-center justify-between">
            <div>
                <h6 class="text-sm font-semibold">Total Transaksi</h6>
                <h2 class="text-3xl font-bold">{{ $stats['total_transactions'] }}</h2>
            </div>
            <i class="fas fa-exchange-alt fa-3x opacity-75"></i>
        </div>

        <div class="bg-yellow-500 text-white rounded-xl shadow p-6 flex items-center justify-between">
            <div>
                <h6 class="text-sm font-semibold">Payout Tertunda</h6>
                <h2 class="text-3xl font-bold">{{ $stats['pending_payouts_admin'] }}</h2>
            </div>
            <i class="fas fa-money-bill-wave fa-3x opacity-75"></i>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    
</div>
@endsection