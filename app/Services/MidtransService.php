<?php
// app/Services/MidtransService.php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

class MidtransService
{
    protected $payoutApiUrl;
    protected $serverKey;
    protected $creatorKey;
    protected $approverKey;

    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');

        $this->serverKey = config('midtrans.server_key');
        $this->creatorKey = config('midtrans.creator_key');
        $this->approverKey = config('midtrans.approver_key');
        
        // Set Payout API URL to sandbox only as per user request
        $this->payoutApiUrl = 'https://app.sandbox.midtrans.com/iris/api/v1/payouts';
    }

    public function createTransaction($orderId, $grossAmount, $customerName, $customerEmail)
    {
        Log::info('[PAYMENT GATEWAY] Creating transaction', [
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail
        ]);

        $params = [
            'transaction_details' => [
                'order_id' => $orderId, // Ini akan menjadi transaction_id dari database
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customerName,
                'email' => $customerEmail,
            ],
        ];
        $snapToken = \Midtrans\Snap::getSnapToken($params);

        Log::info('[PAYMENT GATEWAY] Transaction created successfully', [
            'order_id' => $orderId,
            'snap_token_generated' => !empty($snapToken)
        ]);

        return $snapToken;
    }

    public function handleNotification(Request $request)
    {
        try {
            Log::info('[PAYMENT GATEWAY] Processing payment notification', [
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'headers' => $request->headers->all()
            ]);

            $notification = new Notification();

            Log::info('[PAYMENT GATEWAY] Notification processed successfully', [
                'order_id' => $notification->order_id ?? 'unknown',
                'transaction_status' => $notification->transaction_status ?? 'unknown',
                'payment_type' => $notification->payment_type ?? 'unknown'
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('[PAYMENT GATEWAY] Error processing notification', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            throw new \Exception('Error processing Midtrans notification: ' . $e->getMessage());
        }
    }

    /**
     * Verifikasi signature notification
     */
    public function verifySignature($orderId, $statusCode, $grossAmount, $serverKey)
    {
        $mySignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        return $mySignature;
    }

    /**
     * Create payout request ke Midtrans Payout API
     */
    public function createPayout($payoutId, $amount, $bankAccount, $bankCode, $accountName)
    {
        Log::info('[PAYMENT GATEWAY] Creating payout request', [
            'payout_id' => $payoutId,
            'amount' => $amount,
            'bank_account' => $bankAccount,
            'bank_code' => $bankCode,
            'account_name' => $accountName
        ]);

        // Map bank name or code to supported bank codes
        $bankCodeLower = $this->mapBankCode($bankCode);

        // Validate and map bank code to supported bank codes
        $supportedBanks = $this->getSupportedBanks();
        if (!array_key_exists($bankCodeLower, $supportedBanks)) {
            // Default to 'other' if bank code not supported
            $bankCodeLower = 'other';
        }

        // Sanitize notes to allow only space and alphanumeric characters
        $sanitizedNotes = preg_replace('/[^a-zA-Z0-9 ]/', '', 'Payout for ' . $payoutId);

        $payload = [
            'payouts' => [
                [
                    'beneficiary_name' => $accountName,
                    'beneficiary_account' => $bankAccount,
                    'beneficiary_bank' => $bankCodeLower,
                    'amount' => (string) $amount,
                    'notes' => $sanitizedNotes,
                ]
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->creatorKey . ':')
            ])->withBody(json_encode($payload), 'application/json')->post($this->payoutApiUrl);

            if ($response->successful()) {
                $responseData = $response->object();
                Log::info('[PAYMENT GATEWAY] Payout created successfully', [
                    'payout_id' => $payoutId,
                    'response' => $responseData
                ]);
                return $responseData;
            } else {
                Log::error('[PAYMENT GATEWAY] Payout creation failed', [
                    'payout_id' => $payoutId,
                    'response_body' => $response->body(),
                    'status_code' => $response->status()
                ]);
                throw new \Exception('Payout API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('[PAYMENT GATEWAY] Error creating payout', [
                'payout_id' => $payoutId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Error creating Midtrans payout: ' . $e->getMessage());
        }
    }

    /**
     * Approve payout yang sudah dibuat
     */
    public function approvePayout($approveUrl, $referenceNos, $otp)
    {
        Log::info('[PAYMENT GATEWAY] Approving payout', [
            'approve_url' => $approveUrl,
            'reference_nos' => $referenceNos,
            'otp_provided' => !empty($otp)
        ]);

        $payload = [
            'reference_nos' => [$referenceNos],
            'otp' => $otp
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->approverKey . ':')
            ])->post($approveUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->object();
                Log::info('[PAYMENT GATEWAY] Payout approved successfully', [
                    'reference_nos' => $referenceNos,
                    'response' => $responseData
                ]);
                return $responseData;
            } else {
                Log::error('[PAYMENT GATEWAY] Payout approval failed', [
                    'reference_nos' => $referenceNos,
                    'response_body' => $response->body(),
                    'status_code' => $response->status()
                ]);
                throw new \Exception('Payout Approve Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('[PAYMENT GATEWAY] Error approving payout', [
                'reference_nos' => $referenceNos,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Error approving Midtrans payout: ' . $e->getMessage());
        }
    }

    /**
     * Get payout details
     */
    public function getPayoutDetails($payoutId)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->serverKey . ':')
            ])->get($this->payoutApiUrl . '/' . $payoutId);

            if ($response->successful()) {
                return $response->object();
            } else {
                throw new \Exception('Get Payout Details Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            throw new \Exception('Error getting Midtrans payout details: ' . $e->getMessage());
        }
    }

    /**
     * Handle payout notification callback
     */
    public function handlePayoutNotification(Request $request)
    {
        try {
            $notification = json_decode($request->getContent());
            
            // Verify signature (optional but recommended)
            if ($this->verifyPayoutSignature($request)) {
                return $notification;
            } else {
                throw new \Exception('Invalid signature');
            }
        } catch (\Exception $e) {
            throw new \Exception('Error processing Midtrans payout notification: ' . $e->getMessage());
        }
    }

    /**
     * Verify payout notification signature
     */
    private function verifyPayoutSignature(Request $request)
    {
        $signature = $request->header('Iris-Signature');
        $payload = $request->getContent();
        
        if (!$signature) {
            return false;
        }

        // Use SHA512 hash of payload + merchant key (iris merchant key)
        $merchantKey = config('midtrans.iris_merchant_key', $this->serverKey);
        $expectedSignature = hash('sha512', $payload . $merchantKey);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Map common bank codes or names to supported bank keys
     */
    private function mapBankCode($bankCode)
    {
        $map = [
            'bca' => 'bca',
            'bank central asia' => 'bca',
            'bni' => 'bni',
            'bank negara indonesia' => 'bni',
            'bri' => 'bri',
            'bank rakyat indonesia' => 'bri',
            'mandiri' => 'mandiri',
            'bank mandiri' => 'mandiri',
            'cimb' => 'cimb',
            'cimb niaga' => 'cimb',
            'permata' => 'permata',
            'bank permata' => 'permata',
            'danamon' => 'danamon',
            'bank danamon' => 'danamon',
            'mega' => 'mega',
            'bank mega' => 'mega',
            'bii' => 'bii',
            'bank internasional indonesia' => 'bii',
            'panin' => 'panin',
            'bank panin' => 'panin',
            'bukopin' => 'bukopin',
            'bank bukopin' => 'bukopin',
        ];

        $bankCodeLower = strtolower(trim($bankCode));
        return $map[$bankCodeLower] ?? 'other';
    }

    /**
     * Get list of supported banks
     */
    public function getSupportedBanks()
    {
        return [
            'bca' => 'Bank Central Asia (BCA)',
            'bni' => 'Bank Negara Indonesia (BNI)',
            'bri' => 'Bank Rakyat Indonesia (BRI)',
            'mandiri' => 'Bank Mandiri',
            'cimb' => 'CIMB Niaga',
            'permata' => 'Bank Permata',
            'danamon' => 'Bank Danamon',
            'mega' => 'Bank Mega',
            'bii' => 'Bank Internasional Indonesia (BII)',
            'panin' => 'Bank Panin',
            'bukopin' => 'Bank Bukopin',
            'other' => 'Bank Lainnya'
        ];
    }
}