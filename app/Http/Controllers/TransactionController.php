<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\CarbonCredit;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\MidtransService;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function index()
    {
        $query = Transaction::with(['seller', 'buyer', 'details.carbonCredit']);

        if (Auth::user()->role == 'admin') {
            // Admin dapat melihat semua transaksi
            $transactions = $query->latest()->paginate(10);
        } else {
            // User hanya dapat melihat transaksi mereka sendiri
            $transactions = $query->where(function($q) {
                $q->where('seller_id', Auth::id())
                  ->orWhere('buyer_id', Auth::id());
            })->latest()->paginate(10);
        }

        return view('transactions.index', compact('transactions'));
    }

    public function show(Transaction $transaction)
    {
        try {
            if (!Auth::user()->role == 'admin' && 
                $transaction->seller_id !== Auth::id() && 
                $transaction->buyer_id !== Auth::id()) {
                abort(403, 'Unauthorized action.');
            }

            $transaction->load(['seller', 'buyer', 'details.carbonCredit']);

            return view('transactions.show', compact('transaction'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan.');
        }
    }

    public function create(CarbonCredit $carbonCredit)
    {
        if ($carbonCredit->status !== 'available') {
            return redirect()->back()->with('error', 'Kuota karbon tidak tersedia untuk dibeli.');
        }

        if ($carbonCredit->owner_id === Auth::id()) {
            return redirect()->back()->with('error', 'Anda tidak dapat membeli kuota karbon milik sendiri.');
        }

        // Check if user is admin to determine auto-purchase behavior
        $isAdmin = Auth::user()->role === 'admin';
        
        return view('transactions.create', compact('carbonCredit', 'isAdmin'));
    }

    public function store(Request $request, CarbonCredit $carbonCredit)
    {
        Log::info('[PAYMENT GATEWAY] Starting transaction creation', [
            'user_id' => Auth::id(),
            'user_role' => Auth::user()->role,
            'carbon_credit_id' => $carbonCredit->id,
            'request_data' => $request->all()
        ]);

        // Separate logic for admin and user purchases
        if (Auth::user()->role === 'admin') {
            // Admin automatically purchases the full available quantity
            $quantityToBuy = $carbonCredit->quantity_to_sell;
            
            // No validation needed for quantity since it's automatic
            // Admin purchase logic (no vehicle selection required)

            // Check if the specific carbon credit being sold has enough available quantity
            // Use quantity_to_sell as the base since it represents actual available amount for sale
            $pendingAmount = $carbonCredit->transactionDetails()
                ->whereHas('transaction', function ($query) {
                    $query->where('status', 'pending');
                })
                ->sum('amount');

            $availableAmount = max(0, $carbonCredit->quantity_to_sell - $pendingAmount);

            // Convert to float for accurate comparison
            $quantityToBuyFloat = (float) $quantityToBuy;
            $availableAmountFloat = (float) $availableAmount;

            if ($quantityToBuyFloat > $availableAmountFloat) {
                return redirect()->back()->withErrors(['quantity_to_sell' => 'Jumlah pembelian melebihi kuota karbon yang tersedia.'])->withInput();
            }

            if ($carbonCredit->owner_id === Auth::id()) {
                return redirect()->back()->with('error', 'Anda tidak dapat membeli kuota karbon milik sendiri.');
            }

            DB::beginTransaction();

            try {
                $remainingQuantity = $quantityToBuy;
                $transaction = Transaction::create([
                    'seller_id' => $carbonCredit->owner_id,
                    'buyer_id' => Auth::id(),
                    'transaction_id' => 'TXN-' . Str::random(10),
                    'amount' => $quantityToBuy,
                    'price_per_unit' => $carbonCredit->price_per_unit,
                    'total_amount' => 0, // will calculate below
                    'status' => 'pending',
                ]);

                $totalAmount = 0;

                // Use only the specific carbon credit that was listed for sale
                $credit = $carbonCredit;
                
                // Use consistent logic with the validation above
                $pendingAmount = $credit->transactionDetails()
                    ->whereHas('transaction', function ($query) {
                        $query->where('status', 'pending');
                    })
                    ->sum('amount');

                $availableAmount = max(0, $credit->quantity_to_sell - $pendingAmount);

                $purchaseAmount = min($availableAmount, $remainingQuantity);

                if ($purchaseAmount > 0) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'carbon_credit_id' => $credit->id,
                        'amount' => $purchaseAmount,
                        'price' => $credit->price_per_unit,
                        // No vehicle_id for admin purchase
                    ]);

                    // Decrement quantity_to_sell and amount to reserve quota atomically
                    $credit->quantity_to_sell = max(0, $credit->quantity_to_sell - $purchaseAmount);
                    $credit->amount = max(0, $credit->amount - $purchaseAmount);
                    if ($credit->amount <= 0) {
                        $credit->status = 'sold';
                    }
                    $credit->save();

                    $totalAmount += $purchaseAmount * $credit->price_per_unit;
                    $remainingQuantity -= $purchaseAmount;
                }

                $transaction->total_amount = $totalAmount;
                $transaction->price_per_unit = $totalAmount / $quantityToBuy;
                $transaction->save();

                // Set up Midtrans payment
                $snapToken = $this->midtransService->createTransaction(
                    $transaction->transaction_id,
                    $totalAmount,
                    Auth::user()->name,
                    Auth::user()->email
                );

                $transaction->update(['midtrans_snap_token' => $snapToken]);

                DB::commit();

                Log::info('[PAYMENT GATEWAY] Transaction created successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'total_amount' => $totalAmount,
                    'snap_token' => $snapToken ? 'generated' : 'failed'
                ]);

                Log::info('Proses pembelian berhasil dilakukan (Admin)', [
                    'transaction_id' => $transaction->transaction_id,
                    'buyer_id' => Auth::id(),
                    'seller_id' => $carbonCredit->owner_id,
                    'quantity' => $quantityToBuy,
                    'total_amount' => $totalAmount,
                    'snap_token_generated' => $snapToken ? true : false
                ]);

                return redirect()->route('transactions.payment', $transaction->id);

            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
        } else {
            // User purchase logic (vehicle selection required)
            $request->validate([
                'quantity_to_sell' => 'required|numeric|min:0.01',
                'vehicle_id' => 'required|exists:carbon_credits,id',
            ]);

            $quantityToBuy = $request->input('quantity_to_sell');
            $vehicleId = $request->input('vehicle_id');

            // Check total available quantity across all admin credits
            $adminCredits = CarbonCredit::whereHas('owner', function ($q) {
                $q->where('role', 'admin');
            })
            ->where('status', 'available')
            ->get();

            $totalAvailable = 0;
            foreach ($adminCredits as $credit) {
                $pendingAmount = $credit->transactionDetails()
                    ->whereHas('transaction', function ($query) {
                        $query->where('status', 'pending');
                    })
                    ->sum('amount');

                $availableAmount = max(0, $credit->quantity_to_sell - $pendingAmount);
                $totalAvailable += $availableAmount;
            }

            // Convert to float for accurate comparison
            $quantityToBuyFloat = (float) $quantityToBuy;
            $totalAvailableFloat = (float) $totalAvailable;

            if ($quantityToBuyFloat > $totalAvailableFloat) {
                return redirect()->back()->withErrors(['quantity_to_sell' => 'Jumlah pembelian melebihi kuota karbon yang tersedia.'])->withInput();
            }

            if ($carbonCredit->owner_id === Auth::id()) {
                return redirect()->back()->with('error', 'Anda tidak dapat membeli kuota karbon milik sendiri.');
            }

            DB::beginTransaction();

            try {
                $remainingQuantity = $quantityToBuy;
                $transaction = Transaction::create([
                    'seller_id' => $carbonCredit->owner_id,
                    'buyer_id' => Auth::id(),
                    'transaction_id' => 'TXN-' . Str::random(10),
                    'amount' => $quantityToBuy,
                    'price_per_unit' => $carbonCredit->price_per_unit,
                    'total_amount' => 0, // will calculate below
                    'status' => 'pending',
                ]);

                $totalAmount = 0;

                // Distribute purchase across all available admin credits
                $adminCredits = CarbonCredit::whereHas('owner', function ($q) {
                    $q->where('role', 'admin');
                })
                ->where('status', 'available')
                ->where('quantity_to_sell', '>', 0)
                ->orderBy('id')
                ->get();

                foreach ($adminCredits as $credit) {
                    if ($remainingQuantity <= 0) break;

                    $pendingAmount = $credit->transactionDetails()
                        ->whereHas('transaction', function ($query) {
                            $query->where('status', 'pending');
                        })
                        ->sum('amount');

                    $availableAmount = max(0, $credit->quantity_to_sell - $pendingAmount);

                    $purchaseAmount = min($availableAmount, $remainingQuantity);

                    if ($purchaseAmount > 0) {
                        TransactionDetail::create([
                            'transaction_id' => $transaction->id,
                            'carbon_credit_id' => $credit->id,
                            'amount' => $purchaseAmount,
                            'price' => $credit->price_per_unit,
                            'vehicle_id' => $vehicleId,
                        ]);

                        // Decrement quantity_to_sell and amount to reserve quota atomically
                        $credit->quantity_to_sell = max(0, $credit->quantity_to_sell - $purchaseAmount);
                        $credit->amount = max(0, $credit->amount - $purchaseAmount);
                        if ($credit->amount <= 0) {
                            $credit->status = 'sold';
                        }
                        $credit->save();

                        $totalAmount += $purchaseAmount * $credit->price_per_unit;
                        $remainingQuantity -= $purchaseAmount;
                    }
                }

                $transaction->total_amount = $totalAmount;
                $transaction->price_per_unit = $totalAmount / $quantityToBuy;
                $transaction->save();

                // Set up Midtrans payment
                $snapToken = $this->midtransService->createTransaction(
                    $transaction->transaction_id,
                    $totalAmount,
                    Auth::user()->name,
                    Auth::user()->email
                );

                $transaction->update(['midtrans_snap_token' => $snapToken]);

                DB::commit();

                Log::info('Proses pembelian berhasil dilakukan (User)', [
                    'transaction_id' => $transaction->transaction_id,
                    'buyer_id' => Auth::id(),
                    'quantity' => $quantityToBuy,
                    'total_amount' => $totalAmount,
                    'snap_token_generated' => $snapToken ? true : false
                ]);

                // For user purchase, the purchased carbon credits are already allocated to the user's account
                // through the transaction details, so no additional increment is needed here

                return redirect()->route('transactions.payment', $transaction->id);

            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
            }
        }
    }

    public function showPayment(Transaction $transaction)
    {
        try {
            if ($transaction->buyer_id !== Auth::id() && Auth::user()->role !== 'admin') {
                abort(403, 'Unauthorized action.');
            }

            if ($transaction->status !== 'pending') {
                return redirect()->route('transactions.show', $transaction->id);
            }

            return view('transactions.payment', compact('transaction'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('transactions.index')->with('error', 'Transaksi tidak ditemukan.');
        }
    }

    public function handlePaymentNotification(Request $request)
    {
        Log::info('[PAYMENT GATEWAY] Received payment notification', [
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'request_data' => $request->all()
        ]);

        try {
            $notification = $this->midtransService->handleNotification($request);
            
            $transactionId = $notification->order_id;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus = $notification->fraud_status ?? null;

            $transaction = Transaction::where('transaction_id', $transactionId)->firstOrFail();

            DB::beginTransaction();

            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'challenge') {
                    $transaction->status = 'pending';
                } else if ($fraudStatus == 'accept') {
                    if ($transaction->status !== 'success') {
                        $this->completeTransaction($transaction, $notification);
                    }
                }
            } else if ($transactionStatus == 'settlement') {
                if ($transaction->status !== 'success') {
                    $this->completeTransaction($transaction, $notification);
                }
            } else if (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $transaction->status = 'failed';

                // Restore reserved quota on failed/canceled payment
                foreach ($transaction->details as $detail) {
                    $carbonCredit = $detail->carbonCredit;
                    $carbonCredit->increment('quantity_to_sell', $detail->amount);
                    $carbonCredit->increment('amount', $detail->amount);
                    if ($carbonCredit->amount > 0 && $carbonCredit->status === 'sold') {
                        $carbonCredit->status = 'available';
                        $carbonCredit->save();
                    }
                }
            } else if ($transactionStatus == 'pending') {
                $transaction->status = 'pending';
            }

            $transaction->midtrans_transaction_id = $notification->transaction_id;
            $transaction->payment_method = $notification->payment_type;
            $transaction->save();

            DB::commit();

            Log::info('[PAYMENT GATEWAY] Payment notification processed successfully', [
                'transaction_id' => $transactionId,
                'new_status' => $transaction->status,
                'midtrans_transaction_id' => $notification->transaction_id,
                'payment_method' => $notification->payment_type
            ]);

            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function completeTransaction(Transaction $transaction, $notification)
    {
        Log::info('[PAYMENT GATEWAY] Completing transaction', [
            'transaction_id' => $transaction->transaction_id,
            'current_status' => $transaction->status,
            'notification_status' => $notification->transaction_status
        ]);

        // Check for idempotency - if transaction is already completed, skip
        if ($transaction->status === 'success' && $transaction->paid_at !== null) {
            Log::info('[PAYMENT GATEWAY] Transaction already completed, skipping duplicate processing', [
                'transaction_id' => $transaction->transaction_id
            ]);
            return;
        }

        $transaction->status = 'success';
        $transaction->paid_at = now();
        // $transaction->completed_at = now();

        // Buat payout untuk penjual hanya jika penjual bukan admin
        if ($transaction->seller->role !== 'admin') {
            // Check if payout already exists for this transaction
            $existingPayout = Payout::where('transaction_id', $transaction->id)->first();
            if (!$existingPayout) {
                $adminFee = $transaction->total_amount * 0.05; // Fee admin 5%
                $netAmount = $transaction->total_amount - $adminFee;

                $payout = Payout::create([
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->seller_id,
                    'payout_id' => 'PYT-' . Str::random(10),
                    'amount' => $transaction->total_amount,
                    // 'admin_fee' => $adminFee,
                    'net_amount' => $netAmount,
                    'status' => 'pending',
                ]);

                Log::info('[PAYMENT GATEWAY] Payout created for seller', [
                    'transaction_id' => $transaction->transaction_id,
                    'payout_id' => $payout->payout_id,
                    'seller_id' => $transaction->seller_id,
                    'amount' => $transaction->total_amount,
                    'net_amount' => $netAmount
                ]);
            } else {
                Log::info('[PAYMENT GATEWAY] Payout already exists for transaction, skipping', [
                    'transaction_id' => $transaction->transaction_id,
                    'existing_payout_id' => $existingPayout->payout_id
                ]);
            }
        }

        // No need to decrement quota here since already reserved in pending state

                // Jika pembeli adalah admin, tambahkan kuota karbon ke admin
                if ($transaction->buyer->role === 'admin') {
                    foreach ($transaction->details as $detail) {
                        // Cek apakah admin sudah punya kuota karbon dari proyek yang sama
                        $existingCarbonCredit = CarbonCredit::where('owner_id', $transaction->buyer_id)
                            ->where('title', $detail->carbonCredit->title)
                            ->first();

                        if ($existingCarbonCredit) {
                            // Tambah kuota yang dibeli ke kuota karbon admin yang sudah ada
                            $existingCarbonCredit->increment('amount', $detail->amount);
                            $existingCarbonCredit->increment('quantity_to_sell', $detail->amount);
                        } else {
                            // Buat kuota karbon baru untuk admin
                            CarbonCredit::create([
                                'owner_id' => $transaction->buyer_id,
                                'amount' => $detail->amount,
                                'quantity_to_sell' => $detail->amount,
                                'price_per_unit' => $detail->carbonCredit->price_per_unit,
                                'nomor_kartu_keluarga' => $detail->carbonCredit->nomor_kartu_keluarga,
                                'nik_e_ktp' => $detail->carbonCredit->nik_e_ktp,
                                'pemilik_kendaraan' => $detail->carbonCredit->pemilik_kendaraan,
                                'nrkb' => $detail->carbonCredit->nrkb,
                                'nomor_rangka_5digit' => $detail->carbonCredit->nomor_rangka_5digit,
                                'status' => 'available',
                            ]);
                        }
                        
                        // Adjust original CarbonCredit amount and quantity_to_sell for sold quota
                        // Removed to prevent double decrement causing admin quota to show 0 incorrectly
                        // $originalCredit = $detail->carbonCredit;
                        // $remainingAmount = $originalCredit->amount - $detail->amount;
                        // if ($remainingAmount > 0) {
                        //     $originalCredit->update([
                        //         'amount' => $remainingAmount,
                        //         'quantity_to_sell' => $remainingAmount,
                        //     ]);
                        // } else {
                        //     $originalCredit->update([
                        //         'amount' => 0,
                        //         'quantity_to_sell' => 0,
                        //         'status' => 'sold',
                        //     ]);
                        // }
                    }
                } else {
                    // Jika pembeli bukan admin (user biasa), tambahkan kuota ke user
                    foreach ($transaction->details as $detail) {
                        if ($detail->vehicle_id) {
                            // Tambahkan kuota ke kendaraan yang dipilih user
                            $userVehicle = CarbonCredit::find($detail->vehicle_id);
                            if ($userVehicle) {
                                $userVehicle->increment('amount', $detail->amount);
                                // Jangan langsung increment quantity_to_sell - user harus request sale dulu
                            }
                        }
                    }
                }

    }
}
