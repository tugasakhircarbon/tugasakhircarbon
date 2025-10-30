{{-- resources/views/carbon_credits/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-2xl font-semibold mb-6 text-primary">Tambah Kuota Karbon Baru</h2>
    @if($errors->any())
        <div class="mb-4 p-4 bg-red-100 border border-red-200 rounded text-red-700" role="alert">
            <strong>Terdapat kesalahan dalam form:</strong>
            <ul class="list-disc list-inside mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('carbon-credits.store') }}" novalidate>
        @csrf
        
        <div class="mb-4">
            <label for="nomor_kartu_keluarga" class="block font-semibold mb-1">Nomor Kartu Keluarga <span class="text-red-600">*</span></label>
            <input type="text" id="nomor_kartu_keluarga" name="nomor_kartu_keluarga" value="{{ old('nomor_kartu_keluarga') }}" placeholder="Masukkan nomor kartu keluarga" class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('nomor_kartu_keluarga') border-red-500 @enderror" aria-describedby="nomorKkHelp" aria-invalid="@error('nomor_kartu_keluarga') true @else false @enderror" />
            <p id="nomorKkHelp" class="text-sm text-gray-600 mt-1">Masukkan nomor kartu keluarga sesuai KTP.</p>
            @error('nomor_kartu_keluarga')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="pemilik_kendaraan" class="block font-semibold mb-1">Pemilik Kendaraan <span class="text-red-600">*</span></label>
            <select id="pemilik_kendaraan" name="pemilik_kendaraan" class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('pemilik_kendaraan') border-red-500 @enderror" aria-describedby="pemilikKendaraanHelp" aria-invalid="@error('pemilik_kendaraan') true @else false @enderror">
                <option value="">Pilih Pemilik Kendaraan</option>
                <option value="milik sendiri" {{ old('pemilik_kendaraan') === 'milik sendiri' ? 'selected' : '' }}>Milik Sendiri</option>
                <option value="milik keluarga satu kk" {{ old('pemilik_kendaraan') === 'milik keluarga satu kk' ? 'selected' : '' }}>Milik Keluarga Satu KK</option>
            </select>
            <p id="pemilikKendaraanHelp" class="text-sm text-gray-600 mt-1">Pilih pemilik kendaraan sesuai data.</p>
            @error('pemilik_kendaraan')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </div>

        @php
            $user = auth()->user();
        @endphp

        <div class="mb-4">
            <label for="nik_e_ktp" class="block font-semibold mb-1">NIK e-KTP <span class="text-red-600">*</span></label>
            <input type="text" id="nik_e_ktp" name="nik_e_ktp" value="{{ old('nik_e_ktp') }}" placeholder="Masukkan NIK e-KTP" class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('nik_e_ktp') border-red-500 @enderror" aria-describedby="nikHelp" aria-invalid="@error('nik_e_ktp') true @else false @enderror" />
            <p id="nikHelp" class="text-sm text-gray-600 mt-1">Masukkan NIK e-KTP sesuai data.</p>
            @error('nik_e_ktp')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const pemilikSelect = document.getElementById('pemilik_kendaraan');
                const nomorKkInput = document.getElementById('nomor_kartu_keluarga');
                const nikInput = document.getElementById('nik_e_ktp');

                const userNomorKk = @json($user->nomor_kartu_keluarga);
                const userNik = @json($user->nik_e_ktp);

                function updateFields() {
                    const selected = pemilikSelect.value;
                    if (selected === 'milik sendiri') {
                        nomorKkInput.value = userNomorKk;
                        nomorKkInput.readOnly = true;
                        nikInput.value = userNik;
                        nikInput.readOnly = true;
                    } else if (selected === 'milik keluarga satu kk') {
                        nomorKkInput.value = userNomorKk;
                        nomorKkInput.readOnly = true;
                        nikInput.value = '';
                        nikInput.readOnly = false;
                    } else {
                        nomorKkInput.value = '';
                        nomorKkInput.readOnly = false;
                        nikInput.value = '';
                        nikInput.readOnly = false;
                    }
                }

                pemilikSelect.addEventListener('change', updateFields);

                // Initialize on page load
                updateFields();
            });
        </script>

        <div class="mb-4">
            <label for="nrkb" class="block font-semibold mb-1">NRKB <span class="text-red-600">*</span></label>
            <input type="text" id="nrkb" name="nrkb" value="{{ old('nrkb') }}" placeholder="Masukkan NRKB" class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('nrkb') border-red-500 @enderror" aria-describedby="nrkbHelp" aria-invalid="@error('nrkb') true @else false @enderror" />
            <p id="nrkbHelp" class="text-sm text-gray-600 mt-1">Masukkan nomor registrasi kendaraan.</p>
            @error('nrkb')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="nomor_rangka_5digit" class="block font-semibold mb-1">5 Digit Terakhir Nomor Rangka <span class="text-red-600">*</span></label>
            <input type="text" maxlength="5" id="nomor_rangka_5digit" name="nomor_rangka_5digit" value="{{ old('nomor_rangka_5digit') }}" placeholder="Masukkan 5 digit terakhir nomor rangka" class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('nomor_rangka_5digit') border-red-500 @enderror" aria-describedby="nomorRangkaHelp" aria-invalid="@error('nomor_rangka_5digit') true @else false @enderror" />
            <p id="nomorRangkaHelp" class="text-sm text-gray-600 mt-1">Masukkan 5 digit terakhir nomor rangka kendaraan.</p>
            @error('nomor_rangka_5digit')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="vehicle_type" class="block font-semibold mb-1">Jenis Kendaraan <span class="text-red-600">*</span></label>
            <select id="vehicle_type" name="vehicle_type" class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary @error('vehicle_type') border-red-500 @enderror" aria-describedby="vehicleTypeHelp" aria-invalid="@error('vehicle_type') true @else false @enderror" required>
                <option value="">Pilih Jenis Kendaraan</option>
                <option value="car" {{ old('vehicle_type') == 'car' ? 'selected' : '' }}>Mobil</option>
                <option value="motorcycle" {{ old('vehicle_type') == 'motorcycle' ? 'selected' : '' }}>Motor</option>
            </select>
            <p id="vehicleTypeHelp" class="text-sm text-gray-600 mt-1">Pilih jenis kendaraan untuk menentukan kuota awal.</p>
            @error('vehicle_type')
                <p class="text-red-600 text-sm mt-1" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label class="block font-semibold mb-1">Harga per Unit (Rp)</label>
            <div class="w-full rounded border border-gray-300 px-3 py-2">
                Rp 100
            </div>
        </div>

        <div class="mt-6 p-4 bg-blue-100 rounded text-blue-800">
            <h6 class="font-semibold mb-2 flex items-center space-x-2">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                <span>Informasi:</span>
            </h6>
            <ul class="list-disc list-inside">
                <li>Kuota karbon yang Anda buat akan direview terlebih dahulu oleh admin</li>
                <li>Setelah disetujui, kuota karbon dapat dijual di marketplace</li>
                <li>Pastikan semua informasi yang dimasukkan akurat dan sesuai dengan sertifikasi</li>
            </ul>
        </div>

        <div class="flex justify-between mt-6">
            <a href="{{ route('carbon-credits.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary">
                <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali
            </a>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600">
                <i class="fas fa-save mr-2" aria-hidden="true"></i> Simpan Kuota Karbon
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Price is now fixed at 100, so no need for price calculation
    // This script can be removed or kept for future use if needed
});
</script>
@endsection
