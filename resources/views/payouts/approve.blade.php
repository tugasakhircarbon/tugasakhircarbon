@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto p-6 bg-white rounded-lg shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Approve Payout #{{ $payout->id }}</h1>

    @if(session('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-200 rounded text-red-700 flex items-center space-x-2" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('payouts.approve', $payout->id) }}" novalidate>
        @csrf
        <div class="mb-4">
            <label for="otp" class="block text-sm font-medium text-gray-700">Enter OTP</label>
            <input type="text" name="otp" id="otp" required
                   class="mt-1 block w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('otp') border-red-500 @enderror"
                   aria-invalid="@error('otp') true @else false @enderror"
                   aria-describedby="otpError" />
            @error('otp')
                <p class="mt-1 text-red-600 text-sm" id="otpError" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600">
            <i class="fas fa-check mr-2" aria-hidden="true"></i> Approve Payout
        </button>
    </form>
</div>
@endsection
