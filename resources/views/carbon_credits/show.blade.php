{{-- resources/views/carbon_credits/show.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Detail Kuota Karbon</h4>
                    <div>
                        @if(!Auth::user()->isAdmin() && $carbonCredit->owner_id === Auth::id() || Auth::user()->isAdmin())
                            <a href="{{ route('carbon-credits.edit', $carbonCredit->id) }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        @endif
                        <a href="{{ route('carbon-credits.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Informasi Utama -->
                        <div class="col-md-8">
                            <h2 class="text-primary">{{ $carbonCredit->title }}</h2>
                            
                            <div class="mb-4">
                                <h5>Deskripsi Proyek</h5>
                                <p class="text-justify">{{ $carbonCredit->description }}</p>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Lokasi Proyek</h6>
                                    <p><i class="fas fa-map-marker-alt text-danger"></i> {{ $carbonCredit->project_location }}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Pemilik</h6>
                                    <p><i class="fas fa-user text-info"></i> {{ $carbonCredit->owner->name }}</p>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Periode Proyek</h6>
                                    <p>
                                        <i class="fas fa-calendar text-success"></i> 
                                        {{ $carbonCredit->project_start_date->format('d M Y') }} - 
                                        {{ $carbonCredit->project_end_date->format('d M Y') }}
                                    </p>
                                    <small class="text-muted">
                                        Durasi: {{ $carbonCredit->project_start_date->diffInDays($carbonCredit->project_end_date) }} hari
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <h6>Status</h6>
                                    <p>
                                        @switch($carbonCredit->status)
                                            @case('pending')
                                                <span class="badge bg-warning fs-6">
                                                    <i class="fas fa-clock"></i> Menunggu Persetujuan
                                                </span>
                                                @break
                                            @case('approved')
                                                <span class="badge bg-info fs-6">
                                                    <i class="fas fa-check-circle"></i> Disetujui
                                                </span>
                                                @break
                                            @case('rejected')
                                                <span class="badge bg-danger fs-6">
                                                    <i class="fas fa-times-circle"></i> Ditolak
                                                </span>
                                                @break
                                            @case('available')
                                                <span class="badge bg-success fs-6">
                                                    <i class="fas fa-shopping-cart"></i> Tersedia
                                                </span>
                                                @break
                                            @case('sold')
                                                <span class="badge bg-secondary fs-6">
                                                    <i class="fas fa-sold-out"></i> Terjual
                                                </span>
                                                @break
                                        @endswitch
                                    </p>
                                </div>
                            </div>

                            @if($carbonCredit->certification_type || $carbonCredit->certification_id)
                            <div class="mb-4">
                                <h6>Informasi Sertifikasi</h6>
                                <div class="row">
                                    @if($carbonCredit->certification_type)
                                    <div class="col-md-6">
                                        <strong>Jenis Sertifikasi:</strong> {{ $carbonCredit->certification_type }}
                                    </div>
                                    @endif
                                    @if($carbonCredit->certification_id)
                                    <div class="col-md-6">
                                        <strong>ID Sertifikasi:</strong> {{ $carbonCredit->certification_id }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>

                        <!-- Informasi Finansial -->
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-center">Informasi Finansial</h5>
                                    
                                    <div class="text-center mb-3">
                                        <h2 class="text-success">
                                            Rp {{ number_format($carbonCredit->price_per_unit, 0, ',', '.') }}
                                        </h2>
                                        <small class="text-muted">per ton CO2</small>
                                    </div>

                                    <hr>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Total Kuota:</span>
                                            <strong>{{ number_format($carbonCredit->amount, 2) }} ton</strong>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Tersisa:</span>
                                            <strong class="text-success">{{ number_format($carbonCredit->available_amount, 2) }} ton</strong>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Terjual:</span>
                                            <strong class="text-info">{{ number_format($carbonCredit->amount - $carbonCredit->available_amount, 2) }} ton</strong>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Total Nilai:</span>
                                            <strong class="text-primary">Rp {{ number_format($carbonCredit->total_value, 0, ',', '.') }}</strong>
                                        </div>
                                    </div>

                                    @if($carbonCredit->status === 'available' && $carbonCredit->owner_id !== Auth::id() && $carbonCredit->available_amount > 0)
                                    <div class="d-grid mt-4">
                                        <a href="{{ route('transactions.create', $carbonCredit->id) }}" class="btn btn-success">
                                            <i class="fas fa-shopping-cart"></i> Beli Kuota Ini
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6>Progress Penjualan</h6>
                                    @php
                                        $soldPercentage = $carbonCredit->amount > 0 ? (($carbonCredit->amount - $carbonCredit->available_amount) / $carbonCredit->amount) * 100 : 0;
                                    @endphp
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: {{ $soldPercentage }}%" 
                                             aria-valuenow="{{ $soldPercentage }}" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ number_format($soldPercentage, 1) }}% terjual</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Actions -->
                    @if(Auth::user()->isAdmin())
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-warning">
                                <div class="card-header bg-warning">
                                    <h6 class="mb-0"><i class="fas fa-user-shield"></i> Panel Admin</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        @if($carbonCredit->status === 'pending')
                                        <div class="col-md-6 mb-2">
                                            <form method="POST" action="{{ route('carbon-credits.approve', $carbonCredit->id) }}" 
                                                  style="display: inline;" 
                                                  onsubmit="return confirm('Yakin ingin menyetujui kuota karbon ini?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-check"></i> Setujui Kuota Karbon
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <form method="POST" action="{{ route('carbon-credits.reject', $carbonCredit->id) }}" 
                                                  style="display: inline;"
                                                  onsubmit="return confirm('Yakin ingin menolak kuota karbon ini?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-danger w-100">
                                                    <i class="fas fa-times"></i> Tolak Kuota Karbon
                                                </button>
                                            </form>
                                        </div>
                                        @elseif($carbonCredit->status === 'approved')
                                        <div class="col-md-12">
                                            <form method="POST" action="{{ route('carbon-credits.set-available', $carbonCredit->id) }}" 
                                                  style="display: inline;"
                                                  onsubmit="return confirm('Yakin ingin menjadikan kuota karbon ini tersedia untuk dijual?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-shopping-cart"></i> Set Tersedia untuk Dijual
                                                </button>
                                            </form>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Timestamps -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <small class="text-muted">
                                <div class="row">
                                    <div class="col-md-6">
                                        <i class="fas fa-calendar-plus"></i> Dibuat: {{ $carbonCredit->created_at->format('d M Y H:i') }}
                                    </div>
                                    <div class="col-md-6">
                                        <i class="fas fa-calendar-edit"></i> Diperbarui: {{ $carbonCredit->updated_at->format('d M Y H:i') }}
                                    </div>
                                </div>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection