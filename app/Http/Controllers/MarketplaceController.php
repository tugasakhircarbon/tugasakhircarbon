<?php

namespace App\Http\Controllers;

use App\Models\CarbonCredit;
use App\Services\MqttDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarketplaceController extends Controller
{
    protected $mqttDataService;

    public function __construct(MqttDataService $mqttDataService)
    {
        $this->mqttDataService = $mqttDataService;
    }

    public function index(Request $request)
    {
        $carbonCredits = CarbonCredit::with('owner')
            ->where('status', 'available')
            ->whereHas('owner', function ($q) {
                $q->where('role', 'admin');
            })
            ->get();

        if ($carbonCredits->isEmpty()) {
            return view('marketplace.index')->with('error', 'Tidak ada kuota karbon yang tersedia.');
        }

        $totalAvailableQuantity = 0;
        $totalValue = 0;
        $totalAmount = 0;

        foreach ($carbonCredits as $credit) {
            // Calculate available amount by subtracting pending transactions
            $pendingAmount = $credit->transactionDetails()
                ->whereHas('transaction', function ($query) {
                    $query->where('status', 'pending');
                })
                ->sum('amount');

            $availableAmount = max(0, $credit->quantity_to_sell - $pendingAmount);

            $totalAvailableQuantity += $availableAmount;
            $totalValue += $availableAmount * $credit->price_per_unit;
            $totalAmount += $availableAmount;
        }

        $averagePricePerUnit = $totalAmount > 0 ? $totalValue / $totalAmount : 0;

        // Create a dummy CarbonCredit object to pass to the view with aggregated data
        $carbonCredit = new CarbonCredit();
        $carbonCredit->id = $carbonCredits->first()->id; // Add id for route parameter
        $carbonCredit->quantity_to_sell = $totalAvailableQuantity ?? 0;
        $carbonCredit->price_per_unit = $averagePricePerUnit ?? 0;
        $carbonCredit->owner = $carbonCredits->first()->owner;

        return view('marketplace.index', compact('carbonCredit'));
    }

    public function adminIndex(Request $request)
    {
        // 🎯 VALIDASI MARKETPLACE SEBELUM MENAMPILKAN
        // $this->validateAllMarketplaceItems(); // Temporarily disabled to test approval persistence

        $query = CarbonCredit::with('owner')
            ->where('status', 'available')
            ->where('quantity_to_sell', '>', 0)
            ->whereHas('owner', function ($q) {
                $q->where('role', 'user');
            });

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('project_location', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('location') && $request->location) {
            $query->where('project_location', 'like', '%' . $request->location . '%');
        }

        if ($request->has('min_price') && $request->min_price) {
            $query->where('price_per_unit', '>=', $request->min_price);
        }

        if ($request->has('max_price') && $request->max_price) {
            $query->where('price_per_unit', '<=', $request->max_price);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        $carbonCredits = $query->paginate(12);

        return view('marketplace.admin_index', compact('carbonCredits'))->with('isAdminMarketplace', true);
    }

    public function show(CarbonCredit $carbonCredit)
    {
        // 🎯 VALIDASI MARKETPLACE SEBELUM MENAMPILKAN DETAIL
        $this->validateMarketplaceItem($carbonCredit);

        if ($carbonCredit->status !== 'available') {
            abort(404, 'Kuota karbon tidak tersedia atau telah dihapus dari marketplace.');
        }

        return view('marketplace.show', compact('carbonCredit'));
    }

    /**
     * 🎯 VALIDASI SEMUA ITEM DI MARKETPLACE
     * Cek apakah sisa kuota masih cukup untuk yang dijual
     */
    private function validateAllMarketplaceItems()
    {
        $marketplaceItems = CarbonCredit::where('status', 'available')
            ->whereNotNull('sale_approved_at')
            ->get();

        foreach ($marketplaceItems as $item) {
            $this->validateMarketplaceItem($item);
        }
    }

    /**
     * 🎯 VALIDASI SATU ITEM MARKETPLACE
     * Logika sederhana: Sisa Kuota >= Quantity To Sell → VALID
     */
    private function validateMarketplaceItem(CarbonCredit $carbonCredit)
    {
        // Grace period: Skip validation for items approved in the last hour to prevent immediate reverts on refresh
        if ($carbonCredit->sale_approved_at && $carbonCredit->sale_approved_at->gt(now()->subHour())) {
            Log::info("⏰ Skipping marketplace validation for recent approval (within 1 hour): " . $carbonCredit->device_id);
            return;
        }

        $totalQuota = $carbonCredit->amount;
        $quantityBeingSold = $carbonCredit->quantity_to_sell;
        $dailyEmissions = $carbonCredit->daily_emissions_kg ?? 0;
        
        // 🎯 LOGIKA SEDERHANA: Sisa Kuota = Total - Emisi Harian
        $sisaKuota = $totalQuota - $dailyEmissions;
        
        Log::info("🔍 MARKETPLACE VALIDATION untuk device {$carbonCredit->device_id}:", [
            'punya_total' => $totalQuota,
            'jual' => $quantityBeingSold,
            'pakai_harian' => $dailyEmissions,
            'sisa_kuota' => $sisaKuota,
            'formula' => "{$totalQuota}kg - {$dailyEmissions}kg = {$sisaKuota}kg"
        ]);
        
        // Jika sisa kuota tidak cukup untuk yang dijual → INVALID
        if ($sisaKuota < $quantityBeingSold) {
            
            Log::critical("🚨 MARKETPLACE INVALID - Sisa kuota tidak cukup!", [
                'device_id' => $carbonCredit->device_id,
                'nrkb' => $carbonCredit->nrkb,
                'contoh' => "Punya {$totalQuota}kg → jual {$quantityBeingSold}kg → pakai {$dailyEmissions}kg → sisa {$sisaKuota}kg < jual {$quantityBeingSold}kg = INVALID",
                'sisa_kuota' => $sisaKuota,
                'quantity_sold' => $quantityBeingSold,
                'shortfall' => $quantityBeingSold - $sisaKuota,
                'action' => 'HAPUS DARI MARKETPLACE'
            ]);
            
            // Hapus dari marketplace - kembali ke pending_sale
            $carbonCredit->status = 'pending_sale';
            $carbonCredit->quantity_to_sell = 0;
            $carbonCredit->sale_approved_at = null;
            $carbonCredit->save();
            
            Log::critical("✅ REMOVED FROM MARKETPLACE - device {$carbonCredit->device_id}", [
                'reason' => 'Sisa kuota tidak mencukupi untuk penjualan yang diajukan',
                'old_status' => 'available',
                'new_status' => 'pending_sale',
                'message' => 'User perlu mengajukan ulang dengan kuota yang sesuai kondisi terkini'
            ]);
            
        } else {
            // Sisa kuota masih cukup → VALID, tetap di marketplace
            Log::info("✅ MARKETPLACE MASIH VALID untuk device {$carbonCredit->device_id}", [
                'sisa_kuota' => $sisaKuota,
                'quantity_sold' => $quantityBeingSold,
                'surplus' => $sisaKuota - $quantityBeingSold,
                'status' => 'Tetap di marketplace'
            ]);
        }
        
        // Jika kuota habis total
        if ($sisaKuota <= 0) {
            $carbonCredit->quantity_to_sell = 0;
            $carbonCredit->amount = 0;
            $carbonCredit->status = 'exhausted';
            $carbonCredit->sale_approved_at = null;
            $carbonCredit->save();
            
            Log::critical("🚨 KUOTA HABIS TOTAL - REMOVED FROM MARKETPLACE!", [
                'device_id' => $carbonCredit->device_id,
                'action' => 'Status changed to exhausted'
            ]);
        }
    }
}
