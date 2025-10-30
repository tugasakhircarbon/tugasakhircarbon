<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\CarbonCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class TransactionStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_status_update_via_midtrans_notification()
    {
        // Create seller and buyer users
        $seller = User::factory()->create(['role' => 'user']);
        $buyer = User::factory()->create(['role' => 'user']);

        // Create carbon credit owned by seller
        $carbonCredit = CarbonCredit::factory()->create([
            'owner_id' => $seller->id,
            'status' => 'available',
            'available_amount' => 100,
            'price_per_unit' => 1000,
        ]);

        // Create transaction with pending status
        $transaction = Transaction::create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'transaction_id' => 'TXN-' . Str::random(10),
            'amount' => 10,
            'price_per_unit' => $carbonCredit->price_per_unit,
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        // Create transaction detail
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'carbon_credit_id' => $carbonCredit->id,
            'amount' => 10,
            'price' => $carbonCredit->price_per_unit,
        ]);

        // Simulate Midtrans notification payload for settlement
        $payload = [
            'order_id' => $transaction->transaction_id,
            'transaction_status' => 'settlement',
            'fraud_status' => null,
            'transaction_id' => 'midtrans_txn_123',
            'payment_type' => 'credit_card',
        ];

        // Send POST request to payment notification endpoint
        $response = $this->postJson(route('payment.notification'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Refresh transaction from DB
        $transaction->refresh();

        // Assert transaction status updated to 'paid'
        $this->assertEquals('paid', $transaction->status);
        $this->assertNotNull($transaction->paid_at);
        $this->assertNotNull($transaction->completed_at);
        $this->assertEquals('midtrans_txn_123', $transaction->midtrans_transaction_id);
        $this->assertEquals('credit_card', $transaction->payment_method);

        // Assert carbon credit available amount decreased
        $carbonCredit->refresh();
        $this->assertEquals(90, $carbonCredit->available_amount);
    }
}
