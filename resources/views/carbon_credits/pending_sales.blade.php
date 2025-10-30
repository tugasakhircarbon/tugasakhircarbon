{{-- resources/views/carbon_credits/pending_sales.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>Pengajuan Penjualan Kuota Karbon</h4>
                </div>
                <div class="card-body">
                    @if($pendingSales->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Judul</th>
                                        <th>Pemilik</th>
                                        <th>Jumlah Dijual</th>
                                        <th>Harga/Unit</th>
                                        <th>Total Nilai</th>
                                        <th>Tanggal Pengajuan</th>
                                        <th>Catatan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingSales as $index => $credit)
                                    <tr>
                                        <td>{{ $pendingSales->firstItem() + $index }}</td>
                                        <td>
                                            <strong>{{ $credit->title }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $credit->project_location }}</small>
                                        </td>
                                        <td>{{ $credit->owner->name }}</td>
                                        <td>{{ number_format($credit->quantity_to_sell, 2) }} ton CO2</td>
                                        <td>Rp {{ number_format($credit->sale_price_per_unit, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($credit->quantity_to_sell * $credit->sale_price_per_unit, 0, ',', '.') }}</td>
                                        <td>{{ $credit->sale_requested_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            @if($credit->sale_notes)
                                                <small>{{ Str::limit($credit->sale_notes, 50) }}</small>
                                            @else
                                                <small class="text-muted">Tidak ada catatan</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <form method="POST" action="{{ route('carbon-credits.approve-sale-request', $credit->id) }}" 
                                                      style="display: inline;">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-success" 
                                                            title="Setujui Penjualan"
                                                            onclick="return confirm('Yakin ingin menyetujui pengajuan penjualan ini?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        title="Tolak Penjualan"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rejectModal{{ $credit->id }}">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>

                                            <!-- Modal untuk reject -->
                                            <div class="modal fade" id="rejectModal{{ $credit->id }}" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="{{ route('carbon-credits.reject-sale-request', $credit->id) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Tolak Pengajuan Penjualan</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label for="rejection_reason" class="form-label">Alasan Penolakan</label>
                                                                    <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-center mt-4">
                            {{ $pendingSales->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Tidak ada pengajuan penjualan</h5>
                            <p class="text-muted">Belum ada pengajuan penjualan kuota karbon yang menunggu persetujuan.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
