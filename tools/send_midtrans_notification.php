<?php
// Skrip PHP untuk mengirim simulasi notifikasi webhook Midtrans ke aplikasi Laravel

$endpoint = 'http://localhost:8000/api/transactions/notification'; // Ganti dengan URL endpoint notifikasi Anda
$serverKey = 'SB-Mid-server-W9mqBQcXkTvkii7Wr7vPs9RO'; // Ganti dengan server key Midtrans Anda

// Contoh payload notifikasi (sesuaikan dengan kebutuhan)
$payload = json_encode([
    'order_id' => 'TXN-EXAMPLE1234',
    'transaction_status' => 'settlement',
    'fraud_status' => 'accept',
    'transaction_id' => '1234567890',
    'payment_type' => 'credit_card',
]);

// Hitung signature
$signature = hash_hmac('sha256', $payload, $serverKey);

// Kirim request POST dengan header signature
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Callback-Signature: ' . $signature,
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response HTTP Code: " . $httpCode . PHP_EOL;
echo "Response Body: " . $response . PHP_EOL;
