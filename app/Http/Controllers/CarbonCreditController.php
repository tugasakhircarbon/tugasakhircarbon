<?php

namespace App\Http\Controllers;

use App\Models\CarbonCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CarbonCreditController extends Controller
{
    public function index()
    {
        if (Auth::user()->role == 'admin') {
            $carbonCredits = CarbonCredit::with('owner')
                ->where('owner_id', '!=', Auth::id())
                ->latest()
                ->paginate(10);
        } else {
            $carbonCredits = CarbonCredit::where('owner_id', Auth::id())
                ->latest()
                ->paginate(10);
        }

        return view('carbon_credits.index', compact('carbonCredits'));
    }

    public function vehicles()
    {
        if (Auth::user()->role == 'admin') {
            $vehicles = CarbonCredit::with(['owner' => function($query) {
                $query->where('role', '!=', 'admin');
            }])
            ->whereHas('owner', function($query) {
                $query->where('role', '!=', 'admin');
            })
            ->latest()
            ->paginate(10);
        } else {
            $vehicles = CarbonCredit::where('owner_id', Auth::id())
                ->latest()
                ->paginate(10);
        }

        return view('carbon_credits.vehicles', compact('vehicles'));
    }

    public function create()
    {
        return view('carbon_credits.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pemilik_kendaraan' => 'required|in:milik sendiri,milik keluarga satu kk',
            'nrkb' => 'required|string|max:255',
            'nomor_rangka_5digit' => 'required|string|size:5',
            'vehicle_type' => 'required|in:car,motorcycle',
        ]);

        $validated['owner_id'] = Auth::id();
        $validated['status'] = Auth::user()->role == 'admin' ? 'available' : 'pending';
        $validated['price_per_unit'] = 100; // Set fixed price per unit to 100

        // Set nomor_kartu_keluarga and nik_e_ktp based on pemilik_kendaraan
        $user = Auth::user();
        if ($validated['pemilik_kendaraan'] === 'milik sendiri') {
            $validated['nomor_kartu_keluarga'] = $user->nomor_kartu_keluarga;
            $validated['nik_e_ktp'] = $user->nik_e_ktp;
        } elseif ($validated['pemilik_kendaraan'] === 'milik keluarga satu kk') {
            $validated['nomor_kartu_keluarga'] = $user->nomor_kartu_keluarga;
            $validated['nik_e_ktp'] = $request->input('nik_e_ktp');
        } else {
            $validated['nomor_kartu_keluarga'] = $request->input('nomor_kartu_keluarga');
            $validated['nik_e_ktp'] = $request->input('nik_e_ktp');
        }

        // Do not set amount here; will be set on approval

        CarbonCredit::create($validated);

        return redirect()->route('carbon-credits.index')
            ->with('success', 'Kuota karbon berhasil dibuat dan tersedia untuk dijual.');
    }

    public function show(CarbonCredit $carbonCredit)
    {
        if (Auth::user()->role != 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('carbon_credits.show', compact('carbonCredit'));
    }

    public function edit(CarbonCredit $carbonCredit)
    {
        if (Auth::user()->role != 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('carbon_credits.edit', compact('carbonCredit'));
    }

    public function update(Request $request, CarbonCredit $carbonCredit)
    {
        if (Auth::user()->role != 'admin' && $carbonCredit->owner_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nomor_kartu_keluarga' => 'required|string|max:255',
            'pemilik_kendaraan' => 'required|in:milik sendiri,milik keluarga satu kk',
            'nik_e_ktp' => 'required|string|max:255',
            'nrkb' => 'required|string|max:255',
            'nomor_rangka_5digit' => 'required|string|size:5',
            'vehicle_type' => 'required|in:car,motorcycle',
        ]);

        // Set fixed price per unit to 100
        $validated['price_per_unit'] = 100;

        if (Auth::user()->role == 'admin') {
            $validated['status'] = $request->status;
        }

        // Do not update amount here; amount is set on approval

        $carbonCredit->update($validated);

        return redirect()->route('carbon-credits.index')
            ->with('success', 'Kuota karbon berhasil diperbarui.');
    }

    public function approve(CarbonCredit $carbonCredit)
    {
    
        if (Auth::user()->role != 'admin') {
        abort(403, 'Unauthorized action.');
        }
        
        // Assign initial quota based on vehicle_type
        $initialQuota = 0;
        if ($carbonCredit->vehicle_type === 'car') {
            $initialQuota = 800; // 800 kg CO2eq
        } elseif ($carbonCredit->vehicle_type === 'motorcycle') {
            $initialQuota = 500; // 500 kg CO2eq
        }

        $carbonCredit->update([
            'status' => 'available',
            'amount' => $initialQuota,
        ]);
        
        return redirect()->route('carbon-credits.index')
            ->with('success', 'Kuota karbon berhasil disetujui dan kuota awal telah diberikan.');
    }

    public function reject(CarbonCredit $carbonCredit)
    {
        if (Auth::user()->role != 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $carbonCredit->update(['status' => 'rejected']);

        return redirect()->route('carbon-credits.index')
            ->with('success', 'Kuota karbon ditolak.');
    }

    public function setAvailable(CarbonCredit $carbonCredit)
    {
        if (Auth::user()->role != 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $carbonCredit->update(['status' => 'available']);

        return redirect()->route('carbon-credits.index')
            ->with('success', 'Kuota karbon tersedia untuk dijual.');
    }

    /**
     * Show form to request marketplace listing
     */
    public function requestSale(CarbonCredit $carbonCredit)
    {
        // Method yang sudah ada - untuk menampilkan form
        if (Auth::user()->role != 'admin' && $carbonCredit->owner_id != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($carbonCredit->status !== 'available') {
            return redirect()->route('carbon-credits.index')
                ->with('error', 'Hanya kuota karbon yang tersedia yang dapat diajukan untuk dijual.');
        }

        return view('carbon_credits.request_sale', compact('carbonCredit'));
    }

    public function submitSaleRequest(Request $request, CarbonCredit $carbonCredit)
{
    // Cek authorization
    if (Auth::user()->role != 'admin' && $carbonCredit->owner_id != Auth::id()) {
        abort(403, 'Unauthorized action.');
    }

    // Validasi request dengan nama field yang sesuai
    $request->validate([
        'quantity_to_sell' => 'required|numeric|min:0.01',
    ]);

    // Ensure quantity doesn't exceed effective quota
    $effective = $carbonCredit->effective_quota;
    if ($request->quantity_to_sell > $effective) {
        return back()->with('error', 'Kuota yang diajukan melebihi kuota yang tersedia untuk dijual.');
    }

    // Update dengan field yang sesuai dengan approveSaleRequest
    $carbonCredit->update([
        'status' => 'pending_sale',
        'sale_price_per_unit' => 100, // Set fixed sale price per unit to 100
        'quantity_to_sell' => min($request->quantity_to_sell, $effective),
        'sale_requested_at' => now(),
    ]);

    Log::info('Proses pengajuan penjualan berhasil dilakukan', [
        'carbon_credit_id' => $carbonCredit->id,
        'user_id' => Auth::id(),
        'quantity_to_sell' => $carbonCredit->quantity_to_sell,
        'sale_price_per_unit' => $carbonCredit->sale_price_per_unit,
        'sale_requested_at' => $carbonCredit->sale_requested_at,
    ]);

    return redirect()->route('carbon-credits.index')
        ->with('success', 'Permintaan penjualan berhasil diajukan.');
}



    public function approveSaleRequest(CarbonCredit $carbonCredit)
    {
        if (Auth::user()->role != 'admin') {
            abort(403, 'Unauthorized action.');
        }

        if ($carbonCredit->status !== 'pending_sale') {
            return redirect()->route('carbon-credits.index')
                ->with('error', 'Kuota karbon ini tidak dalam status menunggu persetujuan penjualan.');
        }

        // Recalculate effective quota at approval time and cap quantity_to_sell to ensure validity
        $effectiveQuota = $carbonCredit->effective_quota;
        $cappedQuantity = min($carbonCredit->quantity_to_sell, $effectiveQuota);

        if ($cappedQuantity < $carbonCredit->quantity_to_sell) {
            \Illuminate\Support\Facades\Log::warning("Capped quantity_to_sell for carbon credit {$carbonCredit->id} due to emissions: {$carbonCredit->quantity_to_sell} -> {$cappedQuantity}");
        }

        $carbonCredit->update([
            'status' => 'available',
            'sale_approved_at' => now(),
            // Update harga sesuai pengajuan
            'price_per_unit' => $carbonCredit->sale_price_per_unit,
            'quantity_to_sell' => $cappedQuantity,
            // 'amount' => $carbonCredit->quantity_to_sell, // removed to keep amount unchanged
        ]);

        Log::info('Pengajuan penjualan disetujui oleh admin', [
            'carbon_credit_id' => $carbonCredit->id,
            'admin_id' => Auth::id(),
            'quantity_to_sell' => $cappedQuantity,
            'price_per_unit' => $carbonCredit->price_per_unit,
            'sale_approved_at' => $carbonCredit->sale_approved_at,
        ]);

        return redirect()->route('carbon-credits.index')
            ->with('success', 'Pengajuan penjualan disetujui. Kuota karbon sekarang tersedia di marketplace.');
    }

}
