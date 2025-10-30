<?php
// app/Http/Controllers/PayoutController.php
namespace App\Http\Controllers;

use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MidtransService;

class PayoutController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    public function index()
    {
        if (Auth::user()->role == 'admin') {
            $payouts = Payout::with(['user', 'transaction'])->latest()->paginate(10);
            return view('payouts.admin_index', compact('payouts'));
        } else {
            $payouts = Payout::where('user_id', Auth::id())
                ->with('transaction')
                ->latest()
                ->paginate(10);
            return view('payouts.index', compact('payouts'));
        }
    }

    /**
     * Show the approval form to enter OTP
     */
    public function showApproveForm(Payout $payout)
    {
        // Hanya user yang memiliki payout atau admin yang bisa mengakses
        if (Auth::user()->role != 'admin' && $payout->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($payout->status !== 'created') {
            return redirect()->back()->with('error', 'Payout ini belum dibuat atau sudah diproses.');
        }

        return view('payouts.approve', compact('payout'));
    }

    public function adminIndex()
    {
        $payouts = Payout::with(['user', 'transaction'])->latest()->paginate(10);
        return view('payouts.admin_index', compact('payouts'));
    }

    public function show(Payout $payout)
    {
        if (Auth::user()->role != 'admin' && $payout->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('payouts.show', compact('payout'));
    }

    /**
     * Create payout request (Step 1: Create payout)
     */
    public function create(Payout $payout)
    {
        // Hanya user yang memiliki payout atau admin yang bisa mengakses
        if (Auth::user()->role != 'admin' && $payout->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($payout->status !== 'pending') {
            return redirect()->back()->with('error', 'Payout ini sudah diproses.');
        }

        $user = $payout->user;
        
        if (!$user->bank_account || !$user->bank_name) {
            return redirect()->back()->with('error', 'Informasi rekening bank user belum lengkap.');
        }

        DB::beginTransaction();
        
        try {
            // Create payout request ke Midtrans
            $response = $this->midtransService->createPayout(
                $payout->payout_id,
                $payout->net_amount,
                $user->bank_account,
                $user->bank_name, // bank code
                $user->name
            );

            if (isset($response->payouts) && count($response->payouts) > 0) {
                $payoutData = $response->payouts[0];
                
                // Update payout dengan data dari Midtrans
                $payout->update([
                    'status' => 'created', // Status baru untuk payout yang sudah dibuat tapi belum di-approve
                    'midtrans_payout_id' => $payoutData->reference_no ?? null,
                    'midtrans_response' => json_encode($response),
                    'notes' => 'Payout berhasil dibuat, menunggu approval'
                ]);

                DB::commit();

                // Redirect ke form OTP untuk approval
                return redirect()->route('payouts.approve.form', $payout->id)
                    ->with('success', 'Payout berhasil dibuat. Silakan masukkan OTP untuk approval.');
                    
            } else {
                throw new \Exception('Invalid response from Midtrans Payout API');
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            $payout->update([
                'status' => 'failed',
                'notes' => 'Error: ' . $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Approve payout request (Step 2: Approve payout)
     */
    public function approve(Request $request, Payout $payout)
    {
        // Hanya user yang memiliki payout atau admin yang bisa mengakses
        if (Auth::user()->role != 'admin' && $payout->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($payout->status !== 'created') {
            return redirect()->back()->with('error', 'Payout ini belum dibuat atau sudah diproses.');
        }

        $request->validate([
            'otp' => 'required|string',
        ]);

        DB::beginTransaction();
        
        try {
            // Use fixed approval URL as per Midtrans documentation
            $approveUrl = 'https://app.sandbox.midtrans.com/iris/api/v1/payouts/approve';

            $referenceNos = $payout->midtrans_payout_id ?? 'test-reference-no';
            $otp = $request->input('otp');
            
            // Send approval request
            $response = $this->midtransService->approvePayout($approveUrl, $referenceNos, $otp);

            $payout->update([
                'status' => 'processing',
                'processed_at' => now(),
                'notes' => 'Payout telah disetujui dan sedang diproses'
            ]);

            DB::commit();

            // Redirect berdasarkan role user
            if (Auth::user()->role == 'admin') {
                return redirect()->route('payouts.admin.index')
                    ->with('success', 'Payout berhasil disetujui dan sedang diproses.');
            } else {
                return redirect()->route('payouts.index')
                    ->with('success', 'Payout berhasil disetujui dan sedang diproses.');
            }
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            $payout->update([
                'status' => 'failed',
                'notes' => 'Error saat approval: ' . $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Process payout in one step (Create + Approve)
     */
    public function process(Payout $payout)
    {
        if (Auth::user()->role != 'admin') {
            abort(403, 'Unauthorized action.');
        }

        if ($payout->status !== 'pending') {
            return redirect()->back()->with('error', 'Payout ini sudah diproses.');
        }

        $user = $payout->user;
        
        if (!$user->bank_account || !$user->bank_name) {
            return redirect()->back()->with('error', 'Informasi rekening bank user belum lengkap.');
        }

        DB::beginTransaction();
        
        try {
            // Step 1: Create payout
            $createResponse = $this->midtransService->createPayout(
                $payout->payout_id,
                $payout->net_amount,
                $user->bank_account,
                $user->bank_name,
                $user->name
            );

            if (!isset($createResponse->payouts) || count($createResponse->payouts) === 0) {
                throw new \Exception('Invalid response from Midtrans Payout API');
            }

            $payoutData = $createResponse->payouts[0];
            $approveUrl = $payoutData->approve_url ?? null;

            if (!$approveUrl) {
                throw new \Exception('Approval URL tidak ditemukan');
            }

            // Step 2: Auto approve payout
            // $approveResponse = $this->midtransService->approvePayout($approveUrl);

            // Update payout status
            $payout->update([
                'status' => 'processing',
                'processed_at' => now(),
                'midtrans_payout_id' => $payoutData->reference_no ?? null,
                'midtrans_response' => json_encode($createResponse),
                'notes' => 'Payout telah diproses dan sedang dikirim'
            ]);

            DB::commit();

            return redirect()->route('payouts.index')
                ->with('success', 'Payout berhasil diproses dan sedang dikirim.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            $payout->update([
                'status' => 'failed',
                'notes' => 'Error: ' . $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle payout notification callback dari Midtrans
     */
    public function handlePayoutNotification(Request $request)
    {
        try {
            $notification = $this->midtransService->handlePayoutNotification($request);
            
            // Log notification untuk debugging
            Log::info('Payout Notification Received', (array)$notification);
            
            if (!isset($notification->reference_no)) {
                throw new \Exception('Invalid notification format');
            }

            $payoutId = $notification->reference_no;
            $status = $notification->status ?? 'unknown';

$payout = Payout::where('midtrans_payout_id', $payoutId)->first();

if (!$payout) {
    throw new \Exception('Payout not found: ' . $payoutId);
}

            DB::beginTransaction();

            // Update status berdasarkan notification
            switch ($status) {
                case 'completed':
                case 'success':
                    $payout->update([
                        'status' => 'completed',
                        'notes' => 'Payout berhasil dikirim ke rekening bank'
                    ]);
                    break;
                    
                case 'failed':
                case 'rejected':
                    $payout->update([
                        'status' => 'failed',
                        'notes' => 'Payout gagal: ' . ($notification->reason ?? 'Alasan tidak diketahui')
                    ]);
                    break;
                    
                case 'processing':
                case 'pending':
                    $payout->update([
                        'status' => 'processing',
                        'notes' => 'Payout sedang diproses oleh bank'
                    ]);
                    break;
                    
                default:
                    Log::warning('Unknown payout status: ' . $status, (array)$notification);
                    break;
            }

            DB::commit();

            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payout Notification Error: ' . $e->getMessage(), [
                'request' => $request->all()
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check payout status manually
     */
    public function checkStatus(Payout $payout)
    {
        if (Auth::user()->role != 'admin') {
            abort(403, 'Unauthorized action.');
        }

        if (!$payout->midtrans_payout_id) {
            return redirect()->back()->with('error', 'Payout ini belum memiliki ID Midtrans.');
        }

        try {
            $response = $this->midtransService->getPayoutDetails($payout->midtrans_payout_id);
            
            // Update status berdasarkan response dari Midtrans
            if (isset($response->status)) {
                $status = $response->status;
                
                switch ($status) {
                    case 'completed':
                    case 'success':
                        $payout->update([
                            'status' => 'completed',
                            'notes' => 'Status diperbarui: Payout berhasil'
                        ]);
                        break;
                        
                    case 'failed':
                    case 'rejected':
                        $payout->update([
                            'status' => 'failed',
                            'notes' => 'Status diperbarui: Payout gagal'
                        ]);
                        break;
                        
                    case 'processing':
                    case 'pending':
                        $payout->update([
                            'status' => 'processing',
                            'notes' => 'Status diperbarui: Payout sedang diproses'
                        ]);
                        break;
                }
            }

            return redirect()->route('payouts.show', $payout->id)
                ->with('success', 'Status payout berhasil diperbarui.');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error mengecek status: ' . $e->getMessage());
        }
    }
}
