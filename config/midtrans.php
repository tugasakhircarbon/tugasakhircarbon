<?php

return [
    'merchant_id' => env('MIDTRANS_MERCHANT_ID', 'G532667671'),
    'client_key' => env('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-EZU3PZ9T4DjSNd95'),
    'server_key' => env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-W9mqBQcXkTvkii7Wr7vPs9RO'),
    'iris_merchant_key' => env('IRIS_MERCHANT_KEY', ''),
    'creator_key' => env('MIDTRANS_CREATOR_KEY', ''),
    'approver_key' => env('MIDTRANS_APPROVER_KEY', ''),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds' => env('MIDTRANS_IS_3DS', true),
];